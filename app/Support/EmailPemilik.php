<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Pengaturan;
use App\Models\ReturnRequest;
use App\Notifications\EmailPemilik as Email;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Email ke pemilik toko untuk kejadian penting. Penerima & jenis kejadian diatur di
 * Pengaturan → Kontak & notifikasi. Tanpa SMTP (MAIL_MAILER=log) email hanya tercatat di log.
 */
class EmailPemilik
{
    /** Jenis kejadian yang bisa dipilih + default (pesanan dibayar aktif sejak awal). */
    public const JENIS = [
        NotifikasiAdmin::PESANAN_DIBAYAR => ['Pesanan dibayar (siap dikemas)', true],
        NotifikasiAdmin::PESANAN_BARU => ['Pesanan baru dibuat (belum dibayar)', false],
        NotifikasiAdmin::PEMBAYARAN_DICEK => ['Pembayaran perlu dicek', true],
        NotifikasiAdmin::RETUR_DIAJUKAN => ['Retur diajukan pembeli', true],
    ];

    /** @return array<int, string> */
    public static function penerima(): array
    {
        return self::pecah(Pengaturan::ambil('email_pemilik.alamat'));
    }

    /** "a@x.com, b@y.com" / satu per baris -> daftar email valid, unik. */
    public static function pecah(?string $teks): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $teks))
            ->map(fn ($e) => mb_strtolower(trim($e)))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()->values()->all();
    }

    public static function aktif(string $jenis): bool
    {
        $nilai = Pengaturan::ambil("email_pemilik.{$jenis}");

        return $nilai === null ? (self::JENIS[$jenis][1] ?? false) : $nilai === '1';
    }

    public static function kirim(string $jenis, string $judul, string $isi, ?Order $order = null, ?ReturnRequest $retur = null): void
    {
        try {
            $penerima = self::penerima();
            if (! $penerima || ! self::aktif($jenis) || ! Fitur::aktif('email_pemilik')) {
                return;
            }

            Notification::route('mail', $penerima)->notify(new Email($jenis, $judul, $isi, $order, $retur));
        } catch (Throwable $e) {
            report($e); // email gagal tidak boleh menggagalkan pesanan
        }
    }
}
