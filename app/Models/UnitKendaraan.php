<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kendaraan fisik (satu plat nomor) dari sebuah tipe, berada di satu lokasi pool. */
#[Fillable(['tipe_kendaraan_id', 'lokasi_id', 'plat_nomor', 'tahun', 'warna', 'status', 'catatan'])]
class UnitKendaraan extends Model
{
    use HasUuids;

    public const SIAP = 'siap';

    public const PERAWATAN = 'perawatan';

    public const NONAKTIF = 'nonaktif';

    protected $table = 'unit_kendaraan';

    protected static function booted(): void
    {
        // Plat disimpan seragam: huruf besar, satu spasi antarbagian ("DK 1234 AB").
        static::saving(function (self $u) {
            $u->plat_nomor = self::rapikanPlat((string) $u->plat_nomor);
        });
    }

    protected function casts(): array
    {
        return ['tahun' => 'integer'];
    }

    public function tipe(): BelongsTo
    {
        return $this->belongsTo(TipeKendaraan::class, 'tipe_kendaraan_id');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class);
    }

    public function booking(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BookingSewa::class);
    }

    public static function rapikanPlat(string $plat): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtoupper($plat)));
    }
}
