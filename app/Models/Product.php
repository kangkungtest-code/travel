<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

#[Fillable(['slug', 'category_id', 'nama_terjemahan', 'deskripsi_terjemahan', 'is_active'])]
class Product extends Model
{
    use HasTranslations, HasUuids;

    /** @var array<int, string> Kolom JSON multi-bahasa (id / en / zh-TW). */
    public array $translatable = ['nama_terjemahan', 'deskripsi_terjemahan'];

    protected static function booted(): void
    {
        // Slug dibuat sekali dari nama English; tidak berubah saat nama diganti supaya tautan lama tetap jalan.
        static::saving(function (self $product) {
            $product->slug = filled($product->slug)
                ? self::slugUnik($product->slug, $product->id)
                : self::slugUnik($product->getTranslation('nama_terjemahan', 'en', true) ?: 'produk', $product->id);
        });

        // Hapus foto lewat model supaya file di disk ikut terhapus
        // (cascade di database tidak memicu event model).
        static::deleting(function (self $product) {
            $product->images()->get()->each->delete();
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Tautan lama berbasis UUID tetap bisa dibuka (lalu dialihkan ke slug oleh controller). */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->query()
            ->where($field ?? 'slug', $value)
            ->when(! $field && Str::isUuid($value), fn (Builder $q) => $q->orWhere('id', $value))
            ->first();
    }

    public static function slugUnik(string $sumber, ?string $kecualiId = null): string
    {
        $dasar = Str::limit(Str::slug($sumber), 180, '') ?: 'produk';
        $slug = $dasar;
        for ($i = 2; static::query()->where('slug', $slug)->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))->exists(); $i++) {
            $slug = "{$dasar}-{$i}";
        }

        return $slug;
    }

    /**
     * Cari di nama semua bahasa, tidak peka huruf besar/kecil.
     * (Kolom JSON di MySQL dibandingkan secara biner, jadi perlu CAST + LOWER.)
     */
    public static function cariNama(Builder $query, string $kata): Builder
    {
        $kata = '%'.addcslashes(mb_strtolower($kata), '%_\\').'%';

        return $query->whereRaw('LOWER(CAST(nama_terjemahan AS CHAR)) LIKE ?', [$kata]);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('urutan');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** Nama opsi varian yang dipakai untuk mengaitkan foto ke warna. */
    public const OPSI_WARNA = 'Warna';

    /** Nilai warna yang dipakai varian produk ini, urut sesuai kemunculan. */
    public function daftarWarna(): array
    {
        return $this->variants
            ->map(fn (ProductVariant $v) => $v->opsi[self::OPSI_WARNA] ?? null)
            ->filter()->unique()->values()->all();
    }

    /** Foto untuk warna tertentu; kalau tidak ada, foto pertama produk. */
    public function fotoUntuk(?string $warna): ?ProductImage
    {
        return ($warna ? $this->images->firstWhere('warna', $warna) : null) ?? $this->images->first();
    }
}
