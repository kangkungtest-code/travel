<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Support\AkunPembayaran;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Checkout lewat Orders API v2.
 *
 * Alur: buat order PayPal -> pembeli menyetujui di halaman PayPal (url_bayar) ->
 * kembali ke toko -> capture. Webhook PAYMENT.CAPTURE.COMPLETED dipakai sebagai cadangan
 * kalau pembeli menutup browser sebelum kembali. PayPal tidak mendukung IDR.
 */
class PayPalGateway implements PaymentGateway
{
    public const MATA_UANG = ['USD', 'TWD'];

    /** Mata uang tanpa desimal di PayPal. */
    private const TANPA_DESIMAL = ['TWD', 'JPY', 'HUF'];

    private function cfg(string $k): ?string
    {
        return AkunPembayaran::nilai("paypal.{$k}") ?? ($k === 'mode' ? 'sandbox' : null);
    }

    public function aktif(): bool
    {
        // Di dev/demo hanya boleh sandbox, supaya kredensial live tidak tercampur.
        if ($this->cfg('mode') === 'live' && ! app()->isProduction()) {
            return false;
        }

        return filled($this->cfg('client_id')) && filled($this->cfg('client_secret'));
    }

    public function base(): string
    {
        return $this->cfg('mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    public function mataUangUntuk(Order $order): ?string
    {
        return in_array($order->mata_uang, self::MATA_UANG, true) ? $order->mata_uang : 'USD';
    }

    public static function nilai(float $jumlah, string $mataUang): string
    {
        return in_array($mataUang, self::TANPA_DESIMAL, true)
            ? (string) (int) round($jumlah)
            : number_format($jumlah, 2, '.', '');
    }

    private function token(): string
    {
        return Cache::remember('paypal.token.'.md5((string) $this->cfg('client_id')), now()->addMinutes(50), function () {
            return Http::asForm()->withBasicAuth($this->cfg('client_id'), $this->cfg('client_secret'))
                ->timeout(15)->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
                ->throw()->json('access_token');
        });
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->asJson()->timeout(20)->baseUrl($this->base());
    }

    public function buat(Payment $payment, Order $order, array $opsi = []): void
    {
        try {
            $res = $this->http()
                ->withHeaders(['PayPal-Request-Id' => 'create-'.$payment->id])
                ->post('/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => $order->nomor,
                        'custom_id' => $payment->id,
                        'invoice_id' => $payment->id,
                        'description' => config('toko.nama').' '.$order->nomor,
                        'amount' => [
                            'currency_code' => $payment->mata_uang,
                            'value' => self::nilai((float) $payment->jumlah, $payment->mata_uang),
                        ],
                    ]],
                    'payment_source' => ['paypal' => ['experience_context' => [
                        'brand_name' => config('toko.nama'),
                        'locale' => match (app()->getLocale()) { 'id' => 'id-ID', 'zh_TW' => 'zh-TW', default => 'en-US' },
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                        'return_url' => route('bayar.paypal.kembali', $payment),
                        'cancel_url' => route('bayar.paypal.batal', $payment),
                    ]]],
                ])->throw()->json();
        } catch (RequestException $e) {
            throw new GatewayException('PayPal menolak pembuatan order: '.$e->response->body(), previous: $e);
        }

        $url = collect($res['links'] ?? [])->firstWhere('rel', 'payer-action')['href']
            ?? collect($res['links'] ?? [])->firstWhere('rel', 'approve')['href']
            ?? throw new GatewayException('PayPal tidak mengembalikan link pembayaran.');

        $payment->fill([
            'transaksi_id_eksternal' => $res['id'],
            'url_bayar' => $url,
            'raw_payload' => $res,
        ]);
    }

    /** Dipanggil saat pembeli kembali dari PayPal setelah menyetujui pembayaran. */
    public function capture(Payment $payment): HasilWebhook
    {
        try {
            $res = $this->http()
                ->withHeaders(['PayPal-Request-Id' => 'capture-'.$payment->id])
                ->post("/v2/checkout/orders/{$payment->transaksi_id_eksternal}/capture", (object) [])
                ->throw()->json();
        } catch (RequestException $e) {
            // Sudah di-capture sebelumnya (mis. lewat webhook) -> baca ulang statusnya.
            if (str_contains((string) $e->response->body(), 'ORDER_ALREADY_CAPTURED')) {
                $res = $this->http()->get("/v2/checkout/orders/{$payment->transaksi_id_eksternal}")->throw()->json();
            } else {
                throw new GatewayException('Capture PayPal gagal: '.$e->response->body(), previous: $e);
            }
        }

        $capture = $res['purchase_units'][0]['payments']['captures'][0] ?? null;

        if (($res['status'] ?? null) !== 'COMPLETED' || ($capture['status'] ?? null) !== 'COMPLETED') {
            return new HasilWebhook('gagal', paymentId: $payment->id, transaksiId: $res['id'] ?? null, raw: $res);
        }

        return new HasilWebhook(
            'berhasil',
            paymentId: $payment->id,
            transaksiId: $res['id'],
            jumlah: (float) $capture['amount']['value'],
            mataUang: $capture['amount']['currency_code'],
            idCapture: $capture['id'],
            raw: $res,
        );
    }

    public function bacaWebhook(Request $request): HasilWebhook
    {
        $event = $request->json()->all();
        $verif = $this->http()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => $this->cfg('webhook_id'),
            'webhook_event' => $event,
        ])->json('verification_status');

        if ($verif !== 'SUCCESS' || blank($this->cfg('webhook_id'))) {
            throw new WebhookTidakSah('Tanda tangan webhook PayPal tidak valid.');
        }

        $r = $event['resource'] ?? [];

        return match ($event['event_type'] ?? null) {
            'PAYMENT.CAPTURE.COMPLETED' => new HasilWebhook(
                'berhasil',
                paymentId: $r['custom_id'] ?? null,
                transaksiId: $r['supplementary_data']['related_ids']['order_id'] ?? null,
                jumlah: (float) ($r['amount']['value'] ?? 0),
                mataUang: $r['amount']['currency_code'] ?? null,
                idCapture: $r['id'] ?? null,
                raw: $event,
            ),
            'PAYMENT.CAPTURE.DENIED' => new HasilWebhook('gagal', paymentId: $r['custom_id'] ?? null, raw: $event),
            default => HasilWebhook::abaikan($event),
        };
    }

    public function refund(Payment $payment, string $alasan): string
    {
        if (blank($payment->id_capture)) {
            throw new GatewayException('Pembayaran PayPal ini tidak punya capture id.');
        }

        try {
            return $this->http()
                ->withHeaders(['PayPal-Request-Id' => 'refund-'.$payment->id])
                ->post("/v2/payments/captures/{$payment->id_capture}/refund", [
                    'note_to_payer' => mb_substr($alasan, 0, 255),
                ])->throw()->json('id');
        } catch (RequestException $e) {
            throw new GatewayException('Refund PayPal gagal: '.$e->response->body(), previous: $e);
        }
    }
}
