<?php

namespace App\Http\Controllers;

use App\Actions\Pembayaran\KonfirmasiPembayaranAction;
use App\Models\Payment;
use App\Models\Pengaturan;
use App\Payments\MetodePembayaran;
use App\Payments\WebhookTidakSah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi dari gateway. Endpoint terpisah per gateway, masing-masing
 * memverifikasi tanda tangan / token sendiri sebelum data dipakai.
 */
class WebhookPembayaranController extends Controller
{
    public function paypal(Request $request, KonfirmasiPembayaranAction $konfirmasi): JsonResponse
    {
        return $this->proses($request, Payment::GATEWAY_PAYPAL, $konfirmasi);
    }

    public function xendit(Request $request, KonfirmasiPembayaranAction $konfirmasi): JsonResponse
    {
        // QRIS & VA memakai endpoint dan token yang sama.
        return $this->proses($request, Payment::GATEWAY_QRIS, $konfirmasi);
    }

    private function proses(Request $request, string $kode, KonfirmasiPembayaranAction $konfirmasi): JsonResponse
    {
        $gateway = MetodePembayaran::gateway($kode) ?? abort(404);

        try {
            $hasil = $gateway->bacaWebhook($request);
        } catch (WebhookTidakSah $e) {
            Log::warning("Webhook {$kode} ditolak: ".$e->getMessage(), ['ip' => $request->ip()]);
            self::catat($kode, 'ditolak: token / tanda tangan tidak cocok');

            return response()->json(['ok' => false], 401);
        }

        $payment = $konfirmasi->execute($hasil);

        self::catat($kode, match (true) {
            $hasil->status === 'abaikan' => 'diterima, event diabaikan ('.($hasil->raw['event'] ?? $hasil->raw['event_type'] ?? '?').')',
            $payment === null => 'diterima, transaksi tidak dikenal',
            default => "diterima, pembayaran {$payment->status}",
        });

        return response()->json(['ok' => true]);
    }

    /** Ringkasan webhook terakhir per gateway, untuk membantu cek setup di dev. */
    private static function catat(string $kode, string $hasil): void
    {
        $grup = str_starts_with($kode, 'xendit') ? 'xendit' : $kode;
        Pengaturan::simpan("webhook_terakhir.{$grup}", now()->toIso8601String().'|'.$hasil);
    }

    /** @return array{waktu: \Illuminate\Support\Carbon, hasil: string}|null */
    public static function terakhir(string $grup): ?array
    {
        $nilai = Pengaturan::ambil("webhook_terakhir.{$grup}");
        if (! $nilai || ! str_contains($nilai, '|')) {
            return null;
        }
        [$waktu, $hasil] = explode('|', $nilai, 2);

        return ['waktu' => \Illuminate\Support\Carbon::parse($waktu), 'hasil' => $hasil];
    }
}
