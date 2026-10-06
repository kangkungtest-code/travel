<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nomor', 'user_id', 'address_id', 'alamat_snapshot', 'status', 'sumber_order', 'mata_uang', 'kurs_terpakai',
    'ongkir', 'tarif_pajak_terpakai', 'subtotal', 'total', 'subtotal_idr', 'ongkir_idr', 'total_idr',
    'berat_gram', 'resi', 'kadaluarsa_pada', 'dibayar_pada', 'dikirim_pada', 'selesai_pada',
])]
class Order extends Model
{
    use HasUuids;

    public const STATUS_MENUNGGU_PEMBAYARAN = 'menunggu_pembayaran';
    public const STATUS_DIBAYAR = 'dibayar';
    public const STATUS_DIPROSES = 'diproses';
    public const STATUS_DIKIRIM = 'dikirim';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_KADALUARSA = 'kadaluarsa';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    /**
     * State machine: status -> status berikutnya yang diizinkan. Tidak bisa loncat.
     * (`retur` ditangani tabel return_requests, bukan status order.)
     */
    public const TRANSISI = [
        self::STATUS_MENUNGGU_PEMBAYARAN => [self::STATUS_DIBAYAR, self::STATUS_KADALUARSA, self::STATUS_DIBATALKAN],
        self::STATUS_DIBAYAR => [self::STATUS_DIPROSES, self::STATUS_DIBATALKAN],
        self::STATUS_DIPROSES => [self::STATUS_DIKIRIM],
        self::STATUS_DIKIRIM => [self::STATUS_SELESAI],
        self::STATUS_SELESAI => [],
        self::STATUS_KADALUARSA => [],
        self::STATUS_DIBATALKAN => [],
    ];

    public function bisaPindahKe(string $status): bool
    {
        return in_array($status, self::TRANSISI[$this->status] ?? [], true);
    }

    public function getRouteKeyName(): string
    {
        return 'nomor';
    }

    protected function casts(): array
    {
        return [
            'kurs_terpakai' => 'decimal:10',
            'ongkir' => 'decimal:2',
            'tarif_pajak_terpakai' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'subtotal_idr' => 'decimal:2',
            'ongkir_idr' => 'decimal:2',
            'total_idr' => 'decimal:2',
            'berat_gram' => 'integer',
            'alamat_snapshot' => 'array',
            'kadaluarsa_pada' => 'datetime',
            'dibayar_pada' => 'datetime',
            'dikirim_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    /**
     * Ganti status + catat riwayat. Hanya dipanggil dari Action class (di dalam transaksi),
     * yang sudah mengurus efek sampingnya (stok, refund, notifikasi).
     */
    public function pindahStatus(string $ke, ?User $oleh = null, ?string $catatan = null, array $atribut = []): void
    {
        if (! $this->bisaPindahKe($ke)) {
            throw new \App\Exceptions\TokoException(__('Order status cannot change from :from to :to.', ['from' => $this->status, 'to' => $ke]));
        }

        $dari = $this->status;
        $this->update(['status' => $ke] + $atribut);
        $this->statusHistories()->create(['dari' => $dari, 'ke' => $ke, 'user_id' => $oleh?->id, 'catatan' => $catatan]);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }
}
