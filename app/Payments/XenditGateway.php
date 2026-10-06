<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Support\AkunPembayaran;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Xendit Payment Requests API v3 untuk QRIS dan Virtual Account (IDR saja).
 * Webhook `payment.capture` / `payment.failure` diverifikasi dengan header x-callback-token.
 */
class XenditGateway implements PaymentGateway
{
    public const BASE = 'https://api.xendit.co';

    public const API_VERSION = '2024-11-11';

    public function __construct(private string $jenis) {} // 'qris' atau 'va'

    public function aktif(): bool
    {
        $kunci = (string) AkunPembayaran::nilai('xendit.secret_key');

        // Di dev/demo hanya boleh kunci test (xnd_development_...).
        if (str_starts_with($kunci, 'xnd_production') && ! app()->isProduction()) {
            return false;
        }

        return $kunci !== '' && filled(AkunPembayaran::nilai('xendit.callback_token'));
    }

    public function mataUangUntuk(Order $order): ?string
    {
        return 'IDR';
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth((string) AkunPembayaran::nilai('xendit.secret_key'), '')
            ->withHeaders(['api-version' => self::API_VERSION])
            ->acceptJson()->asJson()->timeout(20)->baseUrl(self::BASE);
    }

    public function buat(Payment $payment, Order $order, array $opsi = []): void
    {
        $bank = strtoupper((string) ($opsi['bank'] ?? ''));
        if ($this->jenis === 'va' && ! in_array($bank, AkunPembayaran::bankVa(), true)) {
            throw new GatewayException("Bank VA tidak dikenal: {$bank}");
        }

        $channel = $this->jenis === 'qris' ? 'QRIS' : "{$bank}_VIRTUAL_ACCOUNT";
        $properti = ['expires_at' => $payment->kadaluarsa_pada?->toIso8601ZuluString()];
        if ($this->jenis === 'va') {
            $properti['display_name'] = mb_substr($order->alamat_snapshot['nama_penerima'] ?? config('toko.nama'), 0, 50);
        }

        try {
            $res = $this->http()
                ->withHeaders(['idempotency-key' => 'create-'.$payment->id])
                ->post('/v3/payment_requests', [
                    'reference_id' => $payment->id,
                    'type' => 'PAY',
                    'country' => 'ID',
                    'currency' => 'IDR',
                    'request_amount' => (int) round((float) $payment->jumlah),
                    'capture_method' => 'AUTOMATIC',
                    'channel_code' => $channel,
                    'channel_properties' => array_filter($properti),
                    'description' => config('toko.nama').' '.$order->nomor,
                    'metadata' => ['order' => $order->nomor],
                ])->throw()->json();
        } catch (RequestException $e) {
            throw new GatewayException('Xendit menolak pembuatan tagihan: '.$e->response->body(), previous: $e);
        }

        $aksi = collect($res['actions'] ?? []);
        $data = $this->jenis === 'qris'
            ? ['qr_string' => $aksi->firstWhere('descriptor', 'QR_STRING')['value'] ?? null]
            : ['bank' => $bank, 'nomor_va' => $aksi->firstWhere('descriptor', 'VIRTUAL_ACCOUNT_NUMBER')['value'] ?? null];

        if (! array_filter($data)) {
            throw new GatewayException('Xendit tidak mengembalikan QR / nomor VA.');
        }

        $payment->fill([
            'transaksi_id_eksternal' => $res['payment_request_id'],
            'data_bayar' => $data,
            'raw_payload' => $res,
        ]);
    }

    public function bacaWebhook(Request $request): HasilWebhook
    {
        $token = (string) AkunPembayaran::nilai('xendit.callback_token');
        if ($token === '' || ! hash_equals($token, (string) $request->header('x-callback-token'))) {
            throw new WebhookTidakSah('x-callback-token Xendit tidak cocok.');
        }

        $event = $request->json()->all();
        $d = $event['data'] ?? [];

        $status = match ($event['event'] ?? null) {
            'payment.capture' => ($d['status'] ?? null) === 'SUCCEEDED' ? 'berhasil' : 'abaikan',
            'payment.failure' => ($d['status'] ?? null) === 'EXPIRED' ? 'kadaluarsa' : 'gagal',
            default => 'abaikan',
        };

        return new HasilWebhook(
            $status,
            paymentId: $d['reference_id'] ?? null,
            transaksiId: $d['payment_request_id'] ?? null,
            jumlah: isset($d['request_amount']) ? (float) $d['request_amount'] : null,
            mataUang: $d['currency'] ?? null,
            idCapture: $d['payment_id'] ?? null,
            raw: $event,
        );
    }

    /**
     * Tanya langsung ke Xendit status tagihan ini. Cadangan kalau webhook tidak sampai,
     * mis. toko kedua yang memakai akun Xendit yang sama (webhook hanya 1 URL per akun).
     * Hasilnya diproses KonfirmasiPembayaranAction persis seperti webhook.
     */
    public function cekStatus(Payment $payment): HasilWebhook
    {
        if (! $this->aktif() || ! $payment->transaksi_id_eksternal) {
            return HasilWebhook::abaikan();
        }

        try {
            $d = $this->http()->get("/v3/payment_requests/{$payment->transaksi_id_eksternal}")->throw()->json();
        } catch (RequestException|\Illuminate\Http\Client\ConnectionException) {
            return HasilWebhook::abaikan();
        }

        // Pastikan tagihan ini memang milik Payment kita.
        if (($d['reference_id'] ?? null) !== $payment->id) {
            return HasilWebhook::abaikan($d);
        }

        $status = match ($d['status'] ?? null) {
            'SUCCEEDED' => 'berhasil',
            'EXPIRED' => 'kadaluarsa',
            'FAILED', 'CANCELED' => 'gagal',
            default => 'abaikan',
        };

        return new HasilWebhook(
            $status,
            paymentId: $payment->id,
            transaksiId: $d['payment_request_id'] ?? $payment->transaksi_id_eksternal,
            jumlah: isset($d['request_amount']) ? (float) $d['request_amount'] : null,
            mataUang: $d['currency'] ?? null,
            raw: ['sumber' => 'cek_status'] + $d,
        );
    }

    /** Simulasi bayar hanya untuk kunci test dan di luar production. */
    public function bisaSimulasi(): bool
    {
        return $this->aktif()
            && ! app()->isProduction()
            && str_starts_with((string) AkunPembayaran::nilai('xendit.secret_key'), 'xnd_development_');
    }

    /**
     * Minta Xendit mensimulasikan pembayaran (mode test). Hasilnya datang lewat
     * webhook payment.capture, sama persis seperti pembayaran sungguhan.
     */
    public function simulasi(Payment $payment): void
    {
        if (! $this->bisaSimulasi() || ! $payment->transaksi_id_eksternal) {
            throw new GatewayException('Simulasi pembayaran tidak tersedia.');
        }

        try {
            $this->http()
                ->post("/v3/payment_requests/{$payment->transaksi_id_eksternal}/simulate", [
                    'amount' => (int) round((float) $payment->jumlah),
                ])->throw();
        } catch (RequestException $e) {
            throw new GatewayException('Simulasi Xendit gagal: '.$e->response->body(), previous: $e);
        }
    }

    public function refund(Payment $payment, string $alasan): string
    {
        try {
            return $this->http()
                ->withHeaders(['idempotency-key' => 'refund-'.$payment->id])
                ->post('/refunds', [
                    'payment_request_id' => $payment->transaksi_id_eksternal,
                    'reference_id' => 'refund-'.$payment->id,
                    'currency' => 'IDR',
                    'amount' => (int) round((float) $payment->jumlah),
                    'reason' => 'REQUESTED_BY_CUSTOMER',
                ])->throw()->json('id');
        } catch (RequestException $e) {
            // Virtual Account umumnya tidak mendukung refund via API -> refund manual.
            throw new GatewayException('Refund Xendit gagal: '.$e->response->body(), previous: $e);
        }
    }
}
