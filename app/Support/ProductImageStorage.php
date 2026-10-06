<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Simpan foto produk sebagai WebP yang sudah diperkecil + thumbnail.
 * File asli (sering 3-8 MB dari HP) tidak disimpan.
 */
class ProductImageStorage
{
    public function store(UploadedFile $file, ?string $direktori = null): string
    {
        $cfg = config('toko.product_images');
        $cfg['directory'] = $direktori ?? $cfg['directory'];

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('File bukan gambar yang bisa dibaca.');
        }

        $source = $this->fixOrientation($source, $file->getRealPath());

        $name = $cfg['directory'].'/'.Str::ulid()->toBase32();
        $disk = Storage::disk($cfg['disk']);

        $disk->put($name.'.webp', $this->encode($source, $cfg['max_width'], $cfg['quality']));
        $disk->put($name.'-thumb.webp', $this->encode($source, $cfg['thumb_width'], $cfg['quality']));


        return $name.'.webp';
    }

    public static function thumbPath(string $path): string
    {
        return preg_replace('/\.webp$/', '-thumb.webp', $path) ?? $path;
    }

    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk(config('toko.product_images.disk'))->delete([$path, self::thumbPath($path)]);
    }

    private function encode(\GdImage $source, int $maxWidth, int $quality): string
    {
        $width = imagesx($source);
        $height = imagesy($source);

        // Batasi sisi terpanjang supaya foto portrait juga ikut kecil.
        $scale = min(1, $maxWidth / max($width, $height));
        $image = $scale < 1
            ? imagescale($source, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC)
            : $source;

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, $quality);
        $data = (string) ob_get_clean();

        return $data;
    }

    /** Foto dari HP sering miring karena orientasi disimpan di EXIF. */
    private function fixOrientation(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }
}
