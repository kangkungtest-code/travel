<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/** Pool / kantor tempat kendaraan diambil & dikembalikan. */
#[Fillable(['nama_terjemahan', 'kota', 'alamat', 'url_peta', 'jam_operasional', 'urutan', 'is_active'])]
class Lokasi extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'lokasi';

    /** @var array<int, string> */
    public array $translatable = ['nama_terjemahan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer', 'is_active' => 'boolean'];
    }

    public function unit(): HasMany
    {
        return $this->hasMany(UnitKendaraan::class);
    }

    public function nama(?string $locale = null): string
    {
        return (string) $this->getTranslation('nama_terjemahan', $locale ?? app()->getLocale());
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('urutan')->orderBy('kota');
    }
}
