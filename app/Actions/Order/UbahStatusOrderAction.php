<?php

namespace App\Actions\Order;

use App\Exceptions\TokoException;
use App\Models\Order;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockLocation;
use App\Models\User;
use App\Notifications\OrderDikirim;
use Illuminate\Support\Facades\DB;

/**
 * Perpindahan status order setelah dibuat, dengan efek sampingnya:
 *  - dibayar  : stok fisik dikurangi dan reservasi dilepas (barang resmi terjual)
 *  - diproses : tanpa efek
 *  - dikirim  : wajib nomor resi, pembeli diberi tahu lewat email
 *  - selesai  : mulai hitung batas waktu retur
 * Batal & kadaluarsa punya Action sendiri (melepas reservasi).
 * Dipakai panel admin sekarang, dan nanti oleh webhook payment (dibayar) / API Flutter.
 */
class UbahStatusOrderAction
{
    public function execute(Order $order, string $ke, ?User $oleh = null, array $data = []): Order
    {
        $order = DB::transaction(function () use ($order, $ke, $oleh, $data) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->bisaPindahKe($ke)) {
                throw new TokoException(__('Order status cannot change from :from to :to.', ['from' => $order->status, 'to' => $ke]));
            }

            $atribut = [];
            switch ($ke) {
                case Order::STATUS_DIBAYAR:
                    $this->kurangiStok($order);
                    $atribut['dibayar_pada'] = now();
                    break;

                case Order::STATUS_DIKIRIM:
                    $resi = trim((string) ($data['resi'] ?? ''));
                    // Booking sewa: "dikirim" = kendaraan diserahkan, tanpa resi.
                    if ($resi === '' && $order->sumber_order !== 'sewa') {
                        throw new TokoException(__('A tracking number is required to mark the order as shipped.'));
                    }
                    $atribut['resi'] = $resi ?: null;
                    $atribut['dikirim_pada'] = now();
                    break;

                case Order::STATUS_SELESAI:
                    $atribut['selesai_pada'] = now();
                    break;

                case Order::STATUS_DIPROSES:
                    break;

                default:
                    throw new TokoException(__('Use the cancel action for this change.'));
            }

            $order->pindahStatus($ke, $oleh, $data['catatan'] ?? null, $atribut);

            return $order;
        });

        if ($ke === Order::STATUS_DIKIRIM && $order->sumber_order !== 'sewa') {
            $order->user?->notify(new OrderDikirim($order));
        }

        if ($ke === Order::STATUS_DIBAYAR) {
            \App\Support\NotifikasiAdmin::pesananDibayar($order, $oleh);
            \App\Support\NotifikasiAdmin::cekStokMenipis(
                $order->items()->with('variant.product', 'variant.stocks')->get()->pluck('variant')->filter()
            );
        }

        return $order;
    }

    /** Barang resmi terjual: jumlah fisik & reservasi sama-sama turun. */
    private function kurangiStok(Order $order): void
    {
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

            $stock->update([
                'jumlah' => max(0, $stock->jumlah - $item->qty),
                'jumlah_reserved' => max(0, $stock->jumlah_reserved - $item->qty),
            ]);

            StockHistory::create([
                'variant_id' => $item->variant_id,
                'perubahan' => -$item->qty,
                'alasan' => 'kurangi',
                'order_id' => $order->id,
            ]);
        }
    }
}
