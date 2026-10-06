<?php

namespace App\Models;

use App\Support\ProductImageStorage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_id', 'path', 'warna', 'urutan'])]
class ProductImage extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        // File ikut terhapus kalau foto dihapus / diganti, supaya tidak ada file yatim di disk.
        static::deleted(function (self $image) {
            app(ProductImageStorage::class)->delete($image->path);
            Storage::disk(config('toko.product_images.disk'))->delete(\App\Support\GambarOg::pathFoto($image));
        });

        static::updated(function (self $image) {
            if ($image->wasChanged('path')) {
                app(ProductImageStorage::class)->delete($image->getOriginal('path'));
                Storage::disk(config('toko.product_images.disk'))->delete(\App\Support\GambarOg::pathFoto($image));
            }
        });
    }

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
