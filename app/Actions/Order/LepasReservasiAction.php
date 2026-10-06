<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockLocation;

/**
 * Lepas stok yang di-reserve sebuah order (dipakai saat batal / kadaluarsa).
 * Harus dipanggil di dalam transaksi yang sudah mengunci baris order.
 */
class LepasReservasiAction
{
    public function execute(Order $order): void
    {
        // Tagihan yang belum dibayar ikut ditutup supaya tidak bisa dibayar lagi.
        $order->payments()->where('status', \App\Models\Payment::PENDING)
            ->update(['status' => \App\Models\Payment::KADALUARSA, 'catatan' => 'Order batal / kadaluarsa']);

        $lokasi = StockLocation::default();

        foreach ($order->items()->orderBy('variant_id')->get() as $item) {
            $stock = Stock::query()
                ->where('variant_id', $item->variant_id)
                ->where('location_id', $lokasi?->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                continue;
            }

            $stock->update(['jumlah_reserved' => max(0, $stock->jumlah_reserved - $item->qty)]);

            StockHistory::create([
                'variant_id' => $item->variant_id,
                'perubahan' => $item->qty,
                'alasan' => 'lepas',
                'order_id' => $order->id,
            ]);
        }
    }
}
