<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Pengaturan;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fitur yang bisa dibuka-tutup per instalasi (diatur Super Admin), lihat config/paket.php.
 *
 * Mematikan fitur hanya menyembunyikan pintu masuknya (menu, halaman, tombol, API);
 * datanya tidak dihapus dan kembali muncul kalau fitur dinyalakan lagi.
 */
class Fitur
{
    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public static function daftar(): array
    {
        return config('paket.fitur');
    }

    /** Fitur yang sudah dibangun (bisa dinyalakan). @return array<int, string> */
    public static function siap(): array
    {
        return array_keys(array_filter(self::daftar(), fn ($f) => $f[2]));
    }

    /** Paket terpilih, atau null = belum pernah diatur (semua fitur menyala). */
    public static function paket(): ?int
    {
        $p = Pengaturan::ambil('fitur.paket');

        return $p !== null && isset(config('paket.paket')[(int) $p]) ? (int) $p : null;
    }

    /** @return array<int, int> */
    public static function daftarPaket(): array
    {
        return array_keys(config('paket.paket'));
    }

    public static function namaPaket(int $nomor): string
    {
        return Pengaturan::ambil("fitur.nama_paket.{$nomor}") ?? "Paket {$nomor}";
    }

    /** @return array<int, string> fitur bawaan paket (hanya yang sudah siap) */
    public static function isiPaket(int $nomor): array
    {
        return array_values(array_intersect(config("paket.paket.{$nomor}", []), self::siap()));
    }

    /** Fitur tambahan (add-on) di luar paket. @return array<int, string> */
    public static function tambahan(): array
    {
        $isi = Pengaturan::ambil('fitur.tambahan');

        return $isi ? array_values(array_intersect(self::siap(), explode(',', $isi))) : [];
    }

    /** @return array<int, string> semua fitur yang menyala sekarang */
    public static function semuaAktif(): array
    {
        $paket = self::paket();
        if ($paket === null) {
            return self::siap();
        }

        return array_values(array_unique([...self::isiPaket($paket), ...self::tambahan()]));
    }

    public static function aktif(string $kunci): bool
    {
        if (! isset(self::daftar()[$kunci])) {
            return true; // bukan fitur yang bisa dimatikan = inti
        }

        return in_array($kunci, self::semuaAktif(), true);
    }

    /**
     * Fitur "hati-hati" dijalankan dengan mempersempit config toko, sehingga semua bagian
     * yang membaca config (pemilih bahasa/mata uang, form admin, validasi, SEO) ikut menyesuaikan:
     *  - multi_bahasa mati      → hanya Bahasa Indonesia (teks bahasa lain tetap tersimpan)
     *  - multi_mata_uang mati   → hanya Rupiah
     *  - kirim_luar_negeri mati → hanya Indonesia (zona negara lain diabaikan, tidak dihapus)
     * Dipanggil saat aplikasi boot & setiap paket diubah. Nilai asli disimpan di toko._asli
     * (ikut ter-cache oleh config:cache), jadi selalu bisa dikembalikan.
     */
    public static function terapkanConfig(): void
    {
        $asli = config('toko._asli') ?? [
            'toko.locales' => config('toko.locales'),
            'toko.required_locales' => config('toko.required_locales'),
            'toko.currencies' => config('toko.currencies'),
            'toko.negara' => config('toko.negara'),
        ];
        config(['toko._asli' => $asli]);
        config($asli);

        try {
            $aktif = self::semuaAktif();
        } catch (\Throwable) {
            return; // database belum siap (mis. saat migrate pertama kali)
        }

        if (! in_array('multi_bahasa', $aktif, true)) {
            config(['toko.locales' => ['id' => $asli['toko.locales']['id'] ?? 'Bahasa Indonesia'], 'toko.required_locales' => ['id']]);
        }
        if (! in_array('multi_mata_uang', $aktif, true)) {
            config(['toko.currencies' => [config('toko.base_currency')]]);
        }
        if (! in_array('kirim_luar_negeri', $aktif, true)) {
            config(['toko.negara' => ['ID' => $asli['toko.negara']['ID'] ?? 'Indonesia']]);
        }
    }

    /** Semua bahasa yang didukung aplikasi (tanpa melihat saklar multi_bahasa). @return array<string, string> */
    public static function semuaBahasa(): array
    {
        return config('toko._asli')['toko.locales'] ?? config('toko.locales');
    }

    /**
     * Menu admin fitur ini tampil? Sama dengan aktif(): fitur yang mati hilang dari panel untuk
     * semua orang (Super Admin pun tidak mengakses data toko). Datanya tetap ada di database.
     */
    public static function terlihatAdmin(string $kunci): bool
    {
        return self::aktif($kunci);
    }

    /**
     * Alasan fitur ini belum boleh dimatikan (transaksi yang masih berjalan).
     *
     * @return array<int, string>
     */
    public static function penghalang(string $kunci): array
    {
        $alasan = [];

        switch ($kunci) {
            case 'retur':
                $n = ReturnRequest::query()->whereIn('status', [ReturnRequest::STATUS_DIAJUKAN, ReturnRequest::STATUS_DISETUJUI])->count();
                $n && $alasan[] = "{$n} pengajuan retur belum selesai";
                break;
            case 'paypal':
                $n = Payment::query()->where('gateway', Payment::GATEWAY_PAYPAL)->where('status', Payment::PENDING)->count();
                $n && $alasan[] = "{$n} pembayaran PayPal masih menunggu";
                break;
            case 'multi_mata_uang':
                $n = Order::query()->where('status', Order::STATUS_MENUNGGU_PEMBAYARAN)
                    ->where('mata_uang', '!=', config('toko.base_currency'))->count();
                $n && $alasan[] = "{$n} pesanan dalam mata uang asing belum dibayar";
                break;
            case 'kirim_luar_negeri':
                $n = Order::query()
                    ->whereIn('status', [Order::STATUS_MENUNGGU_PEMBAYARAN, Order::STATUS_DIBAYAR, Order::STATUS_DIPROSES, Order::STATUS_DIKIRIM])
                    ->where('alamat_snapshot->negara', '!=', 'ID')->count();
                $n && $alasan[] = "{$n} pesanan ke luar negeri belum selesai";
                break;
        }

        return $alasan;
    }

    /**
     * Ganti paket & add-on. Kalau ada fitur yang akan mati tapi masih punya transaksi
     * berjalan, tidak ada yang diubah dan alasannya dikembalikan.
     *
     * @param  array<int, string>  $tambahan
     * @return array<string, array<int, string>> [fitur => alasan] — kosong = berhasil
     */
    public static function terapkan(int $paket, array $tambahan, ?User $oleh = null): array
    {
        $tambahan = array_values(array_diff(array_intersect(self::siap(), $tambahan), self::isiPaket($paket)));
        $sebelum = self::semuaAktif();
        $sesudah = array_values(array_unique([...self::isiPaket($paket), ...$tambahan]));

        $penghalang = [];
        foreach (array_diff($sebelum, $sesudah) as $mati) {
            if ($a = self::penghalang($mati)) {
                $penghalang[$mati] = $a;
            }
        }
        if ($penghalang) {
            return $penghalang;
        }

        $paketLama = self::paket();
        DB::transaction(function () use ($paket, $tambahan, $paketLama, $sebelum, $sesudah, $oleh) {
            Pengaturan::simpan('fitur.paket', (string) $paket);
            Pengaturan::simpan('fitur.tambahan', implode(',', $tambahan) ?: null);
            DB::table('riwayat_fitur')->insert([
                'user_id' => $oleh?->id,
                'paket_dari' => $paketLama,
                'paket_ke' => $paket,
                'dinyalakan' => json_encode(array_values(array_diff($sesudah, $sebelum))),
                'dimatikan' => json_encode(array_values(array_diff($sebelum, $sesudah))),
                'created_at' => now(),
            ]);
        });
        self::terapkanConfig();

        return [];
    }
}
