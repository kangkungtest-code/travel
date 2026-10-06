<?php

namespace App\Models;

use App\Support\Ketersediaan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Detail sewa sebuah order (satu kendaraan per booking). */
#[Fillable(['order_id', 'tipe_kendaraan_id', 'unit_kendaraan_id', 'lokasi_id', 'mode', 'mulai', 'selesai', 'nama_penyewa', 'telepon', 'catatan', 'rincian', 'dokumen', 'sopir', 'serah_terima', 'pengembalian'])]
class BookingSewa extends Model
{
    use HasUuids;

    protected $table = 'booking_sewa';

    protected function casts(): array
    {
        return [
            'mulai' => 'immutable_datetime',
            'selesai' => 'immutable_datetime',
            'rincian' => 'array',
            'dokumen' => 'array',
            'sopir' => 'array',
            'serah_terima' => 'array',
            'pengembalian' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tipe(): BelongsTo
    {
        return $this->belongsTo(TipeKendaraan::class, 'tipe_kendaraan_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitKendaraan::class, 'unit_kendaraan_id');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class);
    }

    /** Booking yang masih memegang unit (belum kadaluarsa/batal/selesai). */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereHas('order', fn (Builder $o) => $o->whereIn('status', Ketersediaan::STATUS_AKTIF));
    }
}
