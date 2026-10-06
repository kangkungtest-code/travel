<?php

namespace App\Models;

use App\Support\Spesifikasi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * Tipe kendaraan yang dipilih pembeli (setara produk di e-commerce), mis. "Toyota Avanza".
 * Mobil fisiknya ada di unit_kendaraan.
 */
#[Fillable(['nama_terjemahan', 'slug', 'deskripsi_terjemahan', 'jenis', 'kursi', 'transmisi', 'bbm', 'bagasi', 'fasilitas', 'urutan', 'is_active'])]
class TipeKendaraan extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'tipe_kendaraan';

    /** @var array<int, string> */
    public array $translatable = ['nama_terjemahan', 'deskripsi_terjemahan'];

    protected static function booted(): void
    {
        static::saving(function (self $t) {
            $sumber = filled($t->slug) ? $t->slug : ($t->getTranslation('nama_terjemahan', 'en', true) ?: 'kendaraan');
            $t->slug = self::slugUnik($sumber, $t->id);
        });
    }

    protected function casts(): array
    {
        return [
            'kursi' => 'integer',
            'bagasi' => 'integer',
            'fasilitas' => 'array',
            'urutan' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function foto(): HasMany
    {
        return $this->hasMany(FotoKendaraan::class)->orderBy('urutan');
    }

    public function unit(): HasMany
    {
        return $this->hasMany(UnitKendaraan::class);
    }

    public function tarif(): HasMany
    {
        return $this->hasMany(Tarif::class);
    }

    /** Tarif aktif untuk mode tertentu, atau null kalau mode itu tidak ditawarkan. */
    public function tarifUntuk(string $mode): ?Tarif
    {
        if (! array_key_exists($mode, config('travel.mode'))) {
            return null; // mode dimatikan lewat Fitur & paket
        }

        return $this->relationLoaded('tarif')
            ? $this->tarif->first(fn (Tarif $t) => $t->mode === $mode && $t->is_active)
            : $this->tarif()->where('mode', $mode)->where('is_active', true)->first();
    }

    /** Harga harian termurah dari tarif aktif ("mulai dari"), atau null. */
    public function hargaMulai(): ?float
    {
        $tarif = ($this->relationLoaded('tarif') ? $this->tarif->where('is_active', true) : $this->tarif()->where('is_active', true)->get())
            ->whereIn('mode', array_keys(config('travel.mode')));

        return $tarif->min('harga_harian');
    }

    public function nama(?string $locale = null): string
    {
        return (string) $this->getTranslation('nama_terjemahan', $locale ?? app()->getLocale());
    }

    /** Ringkasan spesifikasi untuk kartu/daftar, mis. "Mobil · 7 kursi · Otomatis · Bensin". */
    public function ringkasan(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return implode(' · ', array_filter([
            Spesifikasi::label('jenis', $this->jenis, $locale),
            Spesifikasi::kursi($this->kursi, $locale),
            Spesifikasi::label('transmisi', $this->transmisi, $locale),
            Spesifikasi::label('bbm', $this->bbm, $locale),
        ]));
    }

    /** Tipe aktif yang punya tarif aktif dan minimal satu unit siap. */
    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereIn('jenis', array_keys(config('travel.jenis')))
            ->whereHas('tarif', fn (Builder $t) => $t->where('is_active', true)->whereIn('mode', array_keys(config('travel.mode'))))
            ->whereHas('unit', fn (Builder $u) => $u->where('status', UnitKendaraan::SIAP))
            ->orderBy('urutan')->orderBy('slug');
    }

    public static function slugUnik(string $sumber, ?string $kecualiId = null): string
    {
        $dasar = Str::limit(Str::slug($sumber), 160, '') ?: 'kendaraan';
        $slug = $dasar;
        for ($i = 2; static::query()->where('slug', $slug)->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))->exists(); $i++) {
            $slug = "{$dasar}-{$i}";
        }

        return $slug;
    }
}
