<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\Pengaturan;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Akun gateway pembayaran milik toko. Diisi pemilik di panel (Pengaturan → Pembayaran)
 * dan disimpan terenkripsi (APP_KEY) di tabel pengaturan. Kalau belum diisi di panel,
 * dipakai nilai dari .env server (secret GitHub) — dipakai demo/dev.
 */
class AkunPembayaran
{
    /** kunci => [label, rahasia?] — kunci sama dengan config('services.<kunci>'). */
    public const ISIAN = [
        'xendit.secret_key' => ['Secret key Xendit', true],
        'xendit.callback_token' => ['Webhook verification token Xendit', true],
        'paypal.client_id' => ['Client ID PayPal', false],
        'paypal.client_secret' => ['Secret PayPal', true],
        'paypal.webhook_id' => ['Webhook ID PayPal', false],
        'paypal.mode' => ['Mode PayPal', false],
    ];

    /** Metode yang bisa dinyalakan/dimatikan pemilik. */
    public const METODE = [
        Payment::GATEWAY_QRIS => 'QRIS (semua e-wallet & m-banking)',
        Payment::GATEWAY_VA => 'Virtual Account bank',
        Payment::GATEWAY_PAYPAL => 'PayPal (kartu & akun PayPal, untuk pembeli luar negeri)',
    ];

    /** Bank VA yang didukung Xendit (urutan tampil). */
    public const BANK_VA = ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'BSI', 'CIMB'];

    /** Nilai efektif: isian panel, kalau kosong .env server. */
    public static function nilai(string $kunci): ?string
    {
        return self::dariPanel($kunci) ?? (filled($v = config("services.{$kunci}")) ? (string) $v : null);
    }

    /** Dari mana nilai ini berasal: 'panel', 'server', atau null (belum ada). */
    public static function sumber(string $kunci): ?string
    {
        return match (true) {
            self::dariPanel($kunci) !== null => 'panel',
            filled(config("services.{$kunci}")) => 'server',
            default => null,
        };
    }

    public static function dariPanel(string $kunci): ?string
    {
        $simpanan = Pengaturan::ambil("bayar.{$kunci}");
        if ($simpanan === null) {
            return null;
        }
        if (! (self::ISIAN[$kunci][1] ?? false)) {
            return $simpanan;
        }

        try {
            return Crypt::decryptString($simpanan);
        } catch (DecryptException) {
            return null; // APP_KEY berubah: anggap belum diisi.
        }
    }

    public static function simpan(string $kunci, ?string $nilai): void
    {
        $nilai = trim((string) $nilai);
        Pengaturan::simpan("bayar.{$kunci}", $nilai === ''
            ? null
            : ((self::ISIAN[$kunci][1] ?? false) ? Crypt::encryptString($nilai) : $nilai));
    }

    /** Tampilan aman untuk nilai rahasia: xnd_deve…1a2b. */
    public static function samarkan(?string $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        return mb_strlen($nilai) <= 10 ? str_repeat('•', 6) : mb_substr($nilai, 0, 8).'…'.mb_substr($nilai, -4);
    }

    public static function metodeNyala(string $kode): bool
    {
        return Pengaturan::ambil("bayar.aktif.{$kode}") !== '0';
    }

    /** @return array<int, string> */
    public static function bankVa(): array
    {
        $pilihan = Pengaturan::ambil('bayar.xendit.va_banks');
        if ($pilihan === null) {
            return config('services.xendit.va_banks');
        }

        return array_values(array_intersect(self::BANK_VA, explode(',', $pilihan)));
    }

    /**
     * Cek kunci Xendit ke API (tanpa membuat transaksi).
     *
     * @return array{ok: bool, pesan: string}
     */
    public static function tesXendit(?string $kunci): array
    {
        if (blank($kunci)) {
            return ['ok' => false, 'pesan' => 'Secret key belum diisi.'];
        }

        try {
            $kode = Http::withBasicAuth($kunci, '')->timeout(15)->get('https://api.xendit.co/balance')->status();
        } catch (Throwable $e) {
            return ['ok' => false, 'pesan' => 'Tidak bisa menghubungi Xendit: '.$e->getMessage()];
        }

        $jenis = str_starts_with($kunci, 'xnd_production') ? 'LIVE (uang sungguhan)' : 'TEST (uji coba)';

        return match (true) {
            $kode === 401 => ['ok' => false, 'pesan' => 'Kunci ditolak Xendit (salah atau sudah dihapus).'],
            // 403 = kunci benar tapi tidak diberi izin baca saldo — tetap valid.
            in_array($kode, [200, 403], true) => ['ok' => true, 'pesan' => "Kunci dikenali Xendit — mode {$jenis}."],
            default => ['ok' => false, 'pesan' => "Respons Xendit tidak terduga (HTTP {$kode})."],
        };
    }

    /** @return array{ok: bool, pesan: string} */
    public static function tesPayPal(?string $id, ?string $rahasia, string $mode): array
    {
        if (blank($id) || blank($rahasia)) {
            return ['ok' => false, 'pesan' => 'Client ID dan Secret PayPal belum lengkap.'];
        }

        $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        try {
            $res = Http::asForm()->withBasicAuth($id, $rahasia)->timeout(15)
                ->post("{$base}/v1/oauth2/token", ['grant_type' => 'client_credentials']);
        } catch (Throwable $e) {
            return ['ok' => false, 'pesan' => 'Tidak bisa menghubungi PayPal: '.$e->getMessage()];
        }

        return $res->successful()
            ? ['ok' => true, 'pesan' => 'Kredensial PayPal benar (mode '.($mode === 'live' ? 'LIVE' : 'sandbox').').']
            : ['ok' => false, 'pesan' => 'PayPal menolak kredensial ini (HTTP '.$res->status().'). Pastikan mode sandbox/live sesuai.'];
    }
}
