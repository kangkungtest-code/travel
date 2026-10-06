<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/** Kategori produk (3 bahasa). Slug dipakai di URL: /produk?kategori=t-shirts */
#[Fillable(['nama_terjemahan', 'slug', 'urutan', 'is_active'])]
class Category extends Model
{
    use HasTranslations, HasUuids;

    /** @var array<int, string> */
    public array $translatable = ['nama_terjemahan'];

    protected static function booted(): void
    {
        static::saving(function (self $c) {
            $sumber = filled($c->slug) ? $c->slug : ($c->getTranslation('nama_terjemahan', 'en', true) ?: 'kategori');
            $c->slug = self::slugUnik($sumber, $c->id);
        });
    }

    protected function casts(): array
    {
        return ['urutan' => 'integer', 'is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function nama(?string $locale = null): string
    {
        return (string) $this->getTranslation('nama_terjemahan', $locale ?? app()->getLocale());
    }

    /** Kategori aktif yang punya produk aktif, sesuai urutan admin. */
    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('products', fn (Builder $p) => $p->where('is_active', true))
            ->orderBy('urutan')->orderBy('slug');
    }

    public static function slugUnik(string $sumber, ?string $kecualiId = null): string
    {
        $dasar = Str::limit(Str::slug($sumber), 100, '') ?: 'kategori';
        $slug = $dasar;
        for ($i = 2; static::query()->where('slug', $slug)->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))->exists(); $i++) {
            $slug = "{$dasar}-{$i}";
        }

        return $slug;
    }
}
