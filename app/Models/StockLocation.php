<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'is_default'])]
class StockLocation extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'location_id');
    }
}
