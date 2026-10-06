<?php

namespace App\Support;

use App\Models\Pengaturan;

/**
 * Kontak toko & media sosial yang diisi di panel (Pengaturan → Kontak & notifikasi).
 * Semua opsional: yang kosong tidak ditampilkan.
 */
class KontakAdmin
{
    /**
     * Media sosial / marketplace yang didukung: kunci => [nama, pola URL dari handle].
     * Isian boleh handle (@kangkung) atau URL lengkap; marketplace tanpa pola wajib URL lengkap.
     */
    public const MEDSOS = [
        'instagram' => ['Instagram', 'https://instagram.com/%s'],
        'tiktok' => ['TikTok', 'https://www.tiktok.com/@%s'],
        'facebook' => ['Facebook', 'https://facebook.com/%s'],
        'x' => ['X (Twitter)', 'https://x.com/%s'],
        'youtube' => ['YouTube', 'https://www.youtube.com/@%s'],
        'threads' => ['Threads', 'https://www.threads.net/@%s'],
        'shopee' => ['Shopee', null],
        'tokopedia' => ['Tokopedia', 'https://www.tokopedia.com/%s'],
    ];

    /** Nomor WA disimpan hanya angka dengan kode negara, mis. 6281234567890. */
    public static function normalisasiWa(?string $nomor): ?string
    {
        $angka = preg_replace('/\D+/', '', (string) $nomor);
        if ($angka === '') {
            return null;
        }

        // 08xx (format Indonesia) -> 628xx
        return str_starts_with($angka, '0') ? '62'.substr($angka, 1) : $angka;
    }

    public static function urlWa(?string $pesan = null): ?string
    {
        $nomor = Pengaturan::ambil('kontak.wa');

        return $nomor ? 'https://wa.me/'.$nomor.($pesan ? '?text='.rawurlencode($pesan) : '') : null;
    }

    /** ID LINE resmi diawali @ (akun bisnis); selain itu dianggap ID pribadi. */
    public static function urlLine(): ?string
    {
        $id = trim((string) Pengaturan::ambil('kontak.line'));
        if ($id === '') {
            return null;
        }

        return str_starts_with($id, '@')
            ? 'https://line.me/R/ti/p/'.rawurlencode($id)
            : 'https://line.me/R/ti/p/~'.rawurlencode($id);
    }

    public static function urlEmail(?string $subjek = null): ?string
    {
        $email = Pengaturan::ambil('kontak.email');

        return $email ? 'mailto:'.$email.($subjek ? '?subject='.rawurlencode($subjek) : '') : null;
    }

    public static function urlTelepon(): ?string
    {
        $tel = Pengaturan::ambil('kontak.telepon');

        return $tel ? 'tel:+'.$tel : null;
    }

    /** Nomor telepon untuk ditampilkan: +62 812-3456-7890 (sederhana, tanpa library). */
    public static function teksTelepon(): ?string
    {
        $tel = Pengaturan::ambil('kontak.telepon');

        return $tel ? '+'.$tel : null;
    }

    /**
     * Tombol hubungi admin (chatbot, FAQ, footer): WhatsApp, LINE, email, telepon.
     *
     * @return array<int, array{label: string, url: string, jenis: string}>
     */
    public static function tautan(?string $pesan = null): array
    {
        return array_values(array_filter([
            ($u = self::urlWa($pesan)) ? ['jenis' => 'wa', 'label' => __('Chat on WhatsApp'), 'url' => $u] : null,
            ($u = self::urlLine()) ? ['jenis' => 'line', 'label' => __('Chat on LINE'), 'url' => $u] : null,
            ($u = self::urlEmail(config('toko.nama'))) ? ['jenis' => 'email', 'label' => __('Email us'), 'url' => $u] : null,
            ($u = self::urlTelepon()) ? ['jenis' => 'telepon', 'label' => __('Call us'), 'url' => $u] : null,
        ]));
    }

    /** Ubah isian admin (handle atau URL) jadi URL. Null kalau kosong / tidak valid. */
    public static function urlMedsos(string $kunci, ?string $isi): ?string
    {
        $isi = trim((string) $isi);
        if ($isi === '' || ! isset(self::MEDSOS[$kunci])) {
            return null;
        }

        if (preg_match('#^https?://#i', $isi)) {
            return filter_var($isi, FILTER_VALIDATE_URL) ? $isi : null;
        }

        $pola = self::MEDSOS[$kunci][1];
        $handle = ltrim($isi, '@');

        return $pola && preg_match('/^[A-Za-z0-9._-]{1,60}$/', $handle) ? sprintf($pola, $handle) : null;
    }

    /** @return array<int, array{jenis: string, label: string, url: string}> */
    public static function medsos(): array
    {
        $hasil = [];
        foreach (self::MEDSOS as $kunci => [$nama]) {
            if ($url = self::urlMedsos($kunci, Pengaturan::ambil("medsos.{$kunci}"))) {
                $hasil[] = ['jenis' => $kunci, 'label' => $nama, 'url' => $url];
            }
        }

        return $hasil;
    }
}
