<?php

namespace App\Support;

use App\Models\Order;
use App\Models\ReturnRequest;

class TampilanRetur
{
    public static function labelStatus(string $status): string
    {
        return match ($status) {
            ReturnRequest::STATUS_DIAJUKAN => __('Return requested'),
            ReturnRequest::STATUS_DISETUJUI => __('Return approved'),
            ReturnRequest::STATUS_DITOLAK => __('Return declined'),
            ReturnRequest::STATUS_SELESAI => __('Return completed'),
            default => $status,
        };
    }

    public static function penjelasan(ReturnRequest $r): string
    {
        return match ($r->status) {
            ReturnRequest::STATUS_DIAJUKAN => __('We received your return request and will review it within 2 business days.'),
            ReturnRequest::STATUS_DISETUJUI => __('Your return was approved. Send the item back to the address below and enter the tracking number. We cover the shipping cost.'),
            ReturnRequest::STATUS_DITOLAK => __('Your return request was declined.'),
            ReturnRequest::STATUS_SELESAI => $r->penyelesaian === 'ganti_barang'
                ? __('We received the item and are sending you a replacement.')
                : __('We received the item and are refunding your payment.'),
            default => '',
        };
    }

    /** Apakah pembeli masih bisa mengajukan retur untuk order ini. */
    public static function bisaDiajukan(Order $o): bool
    {
        if ($o->status !== Order::STATUS_SELESAI || ! $o->selesai_pada) {
            return false;
        }

        if ($o->selesai_pada->copy()->addDays(config('toko.retur.batas_hari'))->isPast()) {
            return false;
        }

        return $o->returnRequests()->where('status', '!=', ReturnRequest::STATUS_DITOLAK)->doesntExist();
    }
}
