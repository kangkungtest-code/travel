<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Gambar preview saat tautan dibagikan (WhatsApp, LINE, Facebook): JPEG 1200x630.
 * Foto produk disimpan WebP, yang tidak selalu didukung preview chat, jadi dibuat
 * salinan JPEG sekali lalu dipakai ulang (nama file mengikuti id foto).
 */
class GambarOg
{
    public const LEBAR = 1200;

    public const TINGGI = 630;

    private const LATAR = [245, 246, 242]; // --paper

    public static function folder(): string
    {
        return 'og';
    }

    public static function pathFoto(ProductImage $foto): string
    {
        return self::folder().'/'.$foto->id.'.jpg';
    }

    /** URL gambar preview produk (foto pertama), atau null kalau produk belum punya foto. */
    public static function produk(Product $product): ?string
    {
        $foto = $product->images->first();
        if (! $foto) {
            return null;
        }

        $path = self::pathFoto($foto);

        return self::pastikan($path, fn () => [$foto->path]);
    }

    /** Preview untuk beranda/halaman lain: deretan hingga 3 foto produk terbaru. */
    public static function toko(Collection $products): ?string
    {
        $foto = $products->map(fn (Product $p) => $p->images->first())->filter()->take(3)->values();
        if ($foto->isEmpty()) {
            return null;
        }

        $path = self::folder().'/toko-'.substr(md5($foto->pluck('id')->implode('|')), 0, 12).'.jpg';

        return self::pastikan($path, fn () => $foto->pluck('path')->all());
    }

    /** @param  callable(): array<int, string>  $sumber */
    private static function pastikan(string $path, callable $sumber): ?string
    {
        $disk = Storage::disk(config('toko.product_images.disk'));

        if (! $disk->exists($path)) {
            try {
                $jpeg = self::buat(array_map(fn (string $p) => $disk->get($p), $sumber()));
            } catch (\Throwable $e) {
                Log::warning('Gagal membuat gambar preview: '.$e->getMessage(), ['path' => $path]);

                return null;
            }
            if ($jpeg === null) {
                return null;
            }
            $disk->put($path, $jpeg);
        }

        return $disk->url($path);
    }

    /** @param  array<int, string|null>  $isiFoto */
    private static function buat(array $isiFoto): ?string
    {
        $foto = array_values(array_filter(array_map(
            fn (?string $isi) => $isi ? @imagecreatefromstring($isi) : false,
            $isiFoto,
        )));
        if (! $foto) {
            return null;
        }

        $kanvas = imagecreatetruecolor(self::LEBAR, self::TINGGI);
        imagefill($kanvas, 0, 0, imagecolorallocate($kanvas, ...self::LATAR));

        // Satu foto: dimuat utuh (contain). Beberapa foto: tiap kolom diisi penuh (crop tengah).
        $kolom = self::LEBAR / count($foto);
        $isiPenuh = count($foto) > 1;
        foreach ($foto as $i => $img) {
            $w = imagesx($img);
            $h = imagesy($img);
            $x0 = (int) round($i * $kolom);
            $kw = (int) round(($i + 1) * $kolom) - $x0;

            if ($isiPenuh) {
                $skala = max($kw / $w, self::TINGGI / $h);
                $sw = (int) round($kw / $skala);
                $sh = (int) round(self::TINGGI / $skala);
                imagecopyresampled($kanvas, $img, $x0, 0, (int) (($w - $sw) / 2), (int) (($h - $sh) / 2), $kw, self::TINGGI, $sw, $sh);
            } else {
                $skala = min($kw / $w, self::TINGGI / $h);
                $tw = (int) round($w * $skala);
                $th = (int) round($h * $skala);
                imagecopyresampled($kanvas, $img, $x0 + (int) (($kw - $tw) / 2), (int) ((self::TINGGI - $th) / 2), 0, 0, $tw, $th, $w, $h);
            }
        }

        ob_start();
        imagejpeg($kanvas, null, 82);

        return (string) ob_get_clean();
    }
}
