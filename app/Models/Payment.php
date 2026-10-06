<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'gateway', 'status', 'mata_uang', 'jumlah', 'kurs_terpakai', 'jumlah_idr',
    'transaksi_id_eksternal', 'url_bayar', 'data_bayar', 'id_capture', 'raw_payload',
    'dibayar_pada', 'kadaluarsa_pada', 'refund_id', 'catatan',
])]
class Payment extends Model
{
    use HasUuids;

    public const PENDING = 'pending';
    public const BERHASIL = 'berhasil';
    public const GAGAL = 'gagal';
    public const KADALUARSA = 'kadaluarsa';
    public const DIREFUND = 'direfund';

    public const GATEWAY_PAYPAL = 'paypal';
    public const GATEWAY_QRIS = 'xendit_qris';
    public const GATEWAY_VA = 'xendit_va';

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'kurs_terpakai' => 'decimal:10',
            'jumlah_idr' => 'decimal:2',
            'raw_payload' => 'array',
            'data_bayar' => 'array',
            'dibayar_pada' => 'datetime',
            'kadaluarsa_pada' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function masihBerlaku(): bool
    {
        return $this->status === self::PENDING && (! $this->kadaluarsa_pada || $this->kadaluarsa_pada->isFuture());
    }
}
