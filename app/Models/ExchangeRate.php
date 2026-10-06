<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mata_uang_asal', 'mata_uang_tujuan', 'rate', 'margin_persen', 'sumber', 'berlaku_dari'])]
class ExchangeRate extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'margin_persen' => 'decimal:2',
            'berlaku_dari' => 'datetime',
        ];
    }
}
