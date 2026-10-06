<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Harga sewa satu tipe kendaraan untuk satu mode (lepas kunci / dengan sopir), IDR. */
#[Fillable(['tipe_kendaraan_id', 'mode', 'harga_harian', 'harga_12jam', 'harga_per_jam', 'minimal_jam', 'termasuk_bbm', 'is_active'])]
class Tarif extends Model
{
    use HasUuids;

    public const LEPAS_KUNCI = 'lepas_kunci';

    public const SOPIR = 'sopir';

    protected $table = 'tarif';

    protected function casts(): array
    {
        return [
            'harga_harian' => 'float',
            'harga_12jam' => 'float',
            'harga_per_jam' => 'float',
            'minimal_jam' => 'integer',
            'termasuk_bbm' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tipe(): BelongsTo
    {
        return $this->belongsTo(TipeKendaraan::class, 'tipe_kendaraan_id');
    }
}
