<?php

namespace App\Http\Resources\AdminApi;

use Carbon\CarbonInterface;

/** Format nilai yang dipakai semua respons API admin. */
class Format
{
    /** Waktu ISO 8601 di zona toko (WITA, +08:00). */
    public static function waktu(?CarbonInterface $t): ?string
    {
        return $t?->copy()->timezone(config('toko.zona_waktu'))->toIso8601String();
    }

    /** Rupiah: angka mentah + teks siap tampil. */
    public static function rupiah(float|string|null $nilai): array
    {
        $n = (float) $nilai;

        return ['nilai' => round($n, 2), 'teks' => 'Rp'.number_format($n, 0, ',', '.')];
    }
}
