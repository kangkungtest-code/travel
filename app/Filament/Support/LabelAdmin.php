<?php

namespace App\Filament\Support;

use App\Models\Order;
use App\Models\ReturnRequest;

/** Label & warna status untuk panel admin (bahasa Indonesia). */
class LabelAdmin
{
    public const STATUS_ORDER = [
        Order::STATUS_MENUNGGU_PEMBAYARAN => 'Menunggu bayar',
        Order::STATUS_DIBAYAR => 'Dibayar',
        Order::STATUS_DIPROSES => 'Dikonfirmasi',
        Order::STATUS_DIKIRIM => 'Berjalan',
        Order::STATUS_SELESAI => 'Selesai',
        Order::STATUS_KADALUARSA => 'Kadaluarsa',
        Order::STATUS_DIBATALKAN => 'Dibatalkan',
    ];

    public const WARNA_ORDER = [
        Order::STATUS_MENUNGGU_PEMBAYARAN => 'warning',
        Order::STATUS_DIBAYAR => 'info',
        Order::STATUS_DIPROSES => 'info',
        Order::STATUS_DIKIRIM => 'primary',
        Order::STATUS_SELESAI => 'success',
        Order::STATUS_KADALUARSA => 'gray',
        Order::STATUS_DIBATALKAN => 'gray',
    ];

    public const STATUS_RETUR = [
        ReturnRequest::STATUS_DIAJUKAN => 'Diajukan',
        ReturnRequest::STATUS_DISETUJUI => 'Disetujui',
        ReturnRequest::STATUS_DITOLAK => 'Ditolak',
        ReturnRequest::STATUS_SELESAI => 'Selesai',
    ];

    public const WARNA_RETUR = [
        ReturnRequest::STATUS_DIAJUKAN => 'warning',
        ReturnRequest::STATUS_DISETUJUI => 'info',
        ReturnRequest::STATUS_DITOLAK => 'gray',
        ReturnRequest::STATUS_SELESAI => 'success',
    ];

    public static function rupiah(float|string|null $nilai): string
    {
        return 'Rp'.number_format((float) $nilai, 0, ',', '.');
    }
}
