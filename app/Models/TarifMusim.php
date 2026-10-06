<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Kenaikan harga (persen) untuk rentang tanggal, mis. libur Natal & tahun baru. */
#[Fillable(['nama', 'mulai', 'selesai', 'kenaikan_persen', 'is_active'])]
class TarifMusim extends Model
{
    use HasUuids;

    protected $table = 'tarif_musim';

    protected function casts(): array
    {
        return [
            'mulai' => 'date',
            'selesai' => 'date',
            'kenaikan_persen' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
