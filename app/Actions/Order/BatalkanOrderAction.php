<?php

namespace App\Actions\Order;

use App\Exceptions\TokoException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Membatalkan order yang belum dibayar (oleh pembeli atau admin); stok yang di-reserve dilepas. */
class BatalkanOrderAction
{
    public function __construct(private LepasReservasiAction $lepas) {}

    public function execute(Order $order, ?User $oleh = null, ?string $catatan = null): Order
    {
        return DB::transaction(function () use ($order, $oleh, $catatan) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== Order::STATUS_MENUNGGU_PEMBAYARAN) {
                throw new TokoException(__('This order can no longer be cancelled.'));
            }

            $this->lepas->execute($order);
            $order->pindahStatus(Order::STATUS_DIBATALKAN, $oleh, $catatan);

            return $order;
        });
    }
}
