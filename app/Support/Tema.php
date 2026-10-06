<?php

namespace App\Support;

/** Warna & logo toko dari toko/profil.php (lihat config/toko.php). */
class Tema
{
    /** Isi <style> untuk mengganti variabel CSS storefront; '' kalau tidak ada. */
    public static function css(): string
    {
        $baris = [];
        foreach ((array) config('toko.tema') as $var => $nilai) {
            // Hanya nama variabel & nilai warna/ukuran sederhana, supaya tidak bisa merusak <style>.
            if (preg_match('/^--[a-z0-9-]+$/', (string) $var) && preg_match('/^[#a-zA-Z0-9(),.%\s-]+$/', (string) $nilai)) {
                $baris[] = "{$var}: {$nilai};";
            }
        }

        return $baris ? ':root { '.implode(' ', $baris).' }' : '';
    }

    /** URL logo, atau null kalau toko tidak memakai logo. */
    public static function logo(): ?string
    {
        $path = config('toko.logo');

        return $path && is_file(public_path($path)) ? asset($path) : null;
    }

    /** Teks beranda dari profil (per bahasa), atau null untuk teks bawaan. */
    public static function beranda(string $kunci): ?string
    {
        $peta = config("toko.beranda.{$kunci}");
        if (! is_array($peta)) {
            return null;
        }

        return $peta[app()->getLocale()] ?? $peta['en'] ?? null;
    }
}
