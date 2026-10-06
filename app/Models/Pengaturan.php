<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Pengaturan sederhana key-value, di-cache supaya tidak query tiap request. */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kunci', 'nilai'];

    private const CACHE = 'pengaturan.semua';

    public static function ambil(string $kunci, ?string $default = null): ?string
    {
        $semua = Cache::rememberForever(self::CACHE, fn () => static::query()->pluck('nilai', 'kunci')->all());

        return filled($semua[$kunci] ?? null) ? $semua[$kunci] : $default;
    }

    public static function simpan(string $kunci, ?string $nilai): void
    {
        static::query()->updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
        Cache::forget(self::CACHE);
    }
}
