<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['variant_id', 'location_id', 'jumlah', 'jumlah_reserved'])]
class Stock extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['jumlah' => 'integer', 'jumlah_reserved' => 'integer'];
    }

    /** Stok yang masih bisa dijual (belum di-reserve order lain). */
    public function tersedia(): int
    {
        return $this->jumlah - $this->jumlah_reserved;
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }
}
