<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * Halaman kebijakan toko. Slug tetap (privacy, terms, returns, shipping) supaya
 * tautan di footer/checkout tidak putus; admin hanya mengubah isi & tampil/tidaknya.
 */
#[Fillable(['slug', 'judul_terjemahan', 'isi_terjemahan', 'urutan', 'is_active'])]
class HalamanKebijakan extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'halaman_kebijakan';

    /** Penanda bagian draf yang wajib dilengkapi pemilik toko. */
    public const PENANDA_ISI = '[ISI:';

    /** @var array<int, string> */
    public array $translatable = ['judul_terjemahan', 'isi_terjemahan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer', 'is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('urutan');
    }

    /** Isi Markdown -> HTML aman (HTML mentah di-escape, tautan javascript: ditolak). */
    public function isiHtml(?string $locale = null): string
    {
        return Str::markdown((string) $this->getTranslation('isi_terjemahan', $locale ?? app()->getLocale()), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /** Jumlah bagian "[ISI: ...]" yang belum dilengkapi (bahasa yang paling banyak sisanya). */
    public function jumlahPerluDiisi(): int
    {
        return (int) collect($this->getTranslations('isi_terjemahan'))
            ->map(fn ($isi) => substr_count((string) $isi, self::PENANDA_ISI))
            ->max();
    }

    /** Daftar tautan untuk footer & catatan checkout, sesuai bahasa aktif. */
    public static function tautan(): array
    {
        try {
            return static::query()->tampil()->get()
                ->mapWithKeys(fn (self $h) => [$h->slug => [
                    'judul' => $h->getTranslation('judul_terjemahan', app()->getLocale()),
                    'url' => route('kebijakan', $h),
                ]])->all();
        } catch (\Illuminate\Database\QueryException) {
            return []; // tabel belum ada (mis. sebelum migrate)
        }
    }
}
