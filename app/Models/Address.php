<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'label', 'nama_penerima', 'telepon', 'negara', 'kota', 'kode_pos', 'detail_alamat', 'is_default'])]
class Address extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** Data alamat yang disalin ke order (supaya order lama tidak ikut berubah). */
    public function snapshot(): array
    {
        return $this->only(['label', 'nama_penerima', 'telepon', 'negara', 'kota', 'kode_pos', 'detail_alamat']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
