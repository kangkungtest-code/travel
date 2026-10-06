<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'negara', 'is_active'])]
class ShippingZone extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['negara' => 'array', 'is_active' => 'boolean'];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class)->orderBy('berat_min_gram');
    }

    /** Semua kode negara yang bisa dikirimi (dari zona aktif). */
    public static function negaraTersedia(): array
    {
        return static::query()->where('is_active', true)->get()
            ->flatMap(fn (self $z) => $z->negara ?? [])
            ->intersect(array_keys(config('toko.negara'))) // kirim_luar_negeri mati → hanya ID
            ->unique()->sort()->values()->all();
    }

    public static function untukNegara(string $negara): ?self
    {
        if (! array_key_exists(strtoupper($negara), config('toko.negara'))) {
            return null; // negara tidak dilayani (mis. kirim ke luar negeri dimatikan)
        }

        return static::query()->where('is_active', true)->get()
            ->first(fn (self $z) => in_array(strtoupper($negara), $z->negara ?? [], true));
    }
}
