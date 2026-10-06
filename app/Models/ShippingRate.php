<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shipping_zone_id', 'berat_min_gram', 'berat_max_gram', 'tarif_idr'])]
class ShippingRate extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['berat_min_gram' => 'integer', 'berat_max_gram' => 'integer', 'tarif_idr' => 'decimal:2'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
