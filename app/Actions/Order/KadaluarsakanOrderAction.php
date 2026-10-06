<?php

namespace App\Actions\Order;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/** Dipanggil scheduler: order yang lewat batas bayar jadi `kadaluarsa` dan stoknya dilepas. */
class KadaluarsakanOrderAction
{
    public function __construct(private LepasReservasiAction $lepas) {}

    public function execute(): int
    {
        $ids = Order::query()
            ->where('status', Order::STATUS_MENUNGGU_PEMBAYARAN)
            ->where('kadaluarsa_pada', '<=', now())
            ->pluck('id');

        $jumlah = 0;
        foreach ($ids as $id) {
            $jumlah += DB::transaction(function () use ($id) {
                $order = Order::query()->lockForUpdate()->find($id);

                // Bisa saja sudah dibayar/dibatalkan sejak query di atas.
                if (! $order || $order->status !== Order::STATUS_MENUNGGU_PEMBAYARAN || $order->kadaluarsa_pada?->isFuture()) {
                    return 0;
                }

                $this->lepas->execute($order);
                $order->pindahStatus(Order::STATUS_KADALUARSA, null, 'Lewat batas bayar');

                return 1;
            });
        }

        return $jumlah;
    }
}
