<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'sku', 'opsi', 'harga_idr', 'berat_gram'])]
class ProductVariant extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'opsi' => 'array',
            'harga_idr' => 'decimal:2',
            'berat_gram' => 'integer',
        ];
    }

    /** Stok yang bisa dijual (semua lokasi): jumlah fisik - yang sedang di-reserve. */
    public function stokTersedia(): int
    {
        $stocks = $this->relationLoaded('stocks') ? $this->stocks : $this->stocks()->get();

        return (int) $stocks->sum(fn (Stock $s) => $s->jumlah - $s->jumlah_reserved);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'variant_id');
    }

    public function stockHistories(): HasMany
    {
        return $this->hasMany(StockHistory::class, 'variant_id');
    }
}
