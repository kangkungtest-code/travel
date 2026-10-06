<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'alasan', 'foto_bukti', 'status', 'resi_kembali', 'catatan_admin', 'penyelesaian'])]
class ReturnRequest extends Model
{
    use HasUuids;

    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_SELESAI = 'selesai';

    public const PENYELESAIAN = ['refund', 'ganti_barang'];

    protected static function booted(): void
    {
        static::deleted(fn (self $r) => app(\App\Support\ProductImageStorage::class)->delete($r->foto_bukti));
    }

    public function fotoUrl(): ?string
    {
        return $this->foto_bukti ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->foto_bukti) : null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
