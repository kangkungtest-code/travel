<?php

namespace App\Models;

use App\Support\ProductImageStorage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['tipe_kendaraan_id', 'path', 'urutan'])]
class FotoKendaraan extends Model
{
    use HasUuids;

    protected $table = 'foto_kendaraan';

    protected static function booted(): void
    {
        // File ikut terhapus kalau foto dihapus / diganti.
        static::deleted(fn (self $foto) => app(ProductImageStorage::class)->delete($foto->path));
        static::updated(function (self $foto) {
            if ($foto->wasChanged('path')) {
                app(ProductImageStorage::class)->delete($foto->getOriginal('path'));
            }
        });
    }

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function tipe(): BelongsTo
    {
        return $this->belongsTo(TipeKendaraan::class, 'tipe_kendaraan_id');
    }

    public function url(): string
    {
        return Storage::disk(config('toko.product_images.disk'))->url($this->path);
    }

    public function thumbUrl(): string
    {
        return Storage::disk(config('toko.product_images.disk'))->url(ProductImageStorage::thumbPath($this->path));
    }
}
