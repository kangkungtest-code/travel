<?php

namespace App\Actions\Order;

use App\Exceptions\TokoException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockLocation;
use App\Models\User;
use App\Notifications\OrderDibuat;
use App\Support\HitungOngkir;
use App\Support\Kurs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Checkout: keranjang user -> order `menunggu_pembayaran`, stok di-reserve.
 *
 * Semua dalam satu transaksi dengan row lock di baris stok, supaya dua pembeli
 * yang checkout bersamaan tidak bisa mengambil stok yang sama.
 * Harga item disimpan dalam IDR; subtotal/ongkir/total disimpan dalam mata uang
 * order (snapshot kurs) dan juga dalam IDR untuk laporan.
 */
class BuatOrderAction
{
    public function __construct(
        private HitungOngkir $ongkir,
        private Kurs $kurs,
    ) {}

    public function execute(User $user, Address $alamat, string $mataUang): Order
    {
        if ($alamat->user_id !== $user->id) {
            throw new TokoException(__('Choose one of your saved addresses.'));
        }

        $order = DB::transaction(function () use ($user, $alamat, $mataUang) {
            $cart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $items = $cart?->items()->with('variant.product')->get() ?? collect();

            if ($items->isEmpty()) {
                throw new TokoException(__('Your cart is empty.'));
            }

            $lokasi = StockLocation::default() ?? throw new TokoException(__('Checkout is temporarily unavailable.'));

            // Kunci baris stok dengan urutan tetap untuk menghindari deadlock.
            $stocks = Stock::query()
                ->where('location_id', $lokasi->id)
                ->whereIn('variant_id', $items->pluck('variant_id')->sort()->values())
                ->orderBy('variant_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('variant_id');

            foreach ($items as $item) {
                $this->cekItem($item, $stocks->get($item->variant_id));
            }

            $berat = (int) $items->sum(fn (CartItem $i) => $i->variant->berat_gram * $i->qty);
            $ongkirIdr = $this->ongkir->hitung($alamat->negara, $berat)
                ?? throw new TokoException(__('We can\'t ship to this address yet.'));

            $mataUang = $this->kurs->mataUangEfektif($mataUang);
            $subtotalIdr = (float) $items->sum(fn (CartItem $i) => (float) $i->variant->harga_idr * $i->qty);
            $subtotal = (float) $items->sum(fn (CartItem $i) => $this->kurs->konversi((float) $i->variant->harga_idr, $mataUang) * $i->qty);
            $ongkir = $this->kurs->konversi($ongkirIdr, $mataUang);

            $order = Order::create([
                'nomor' => $this->nomorBaru(),
                'user_id' => $user->id,
                'address_id' => $alamat->id,
                'alamat_snapshot' => $alamat->snapshot(),
                'status' => Order::STATUS_MENUNGGU_PEMBAYARAN,
                'sumber_order' => 'online',
                'mata_uang' => $mataUang,
                'kurs_terpakai' => $this->kurs->rateEfektif($mataUang),
                'subtotal' => $subtotal,
                'ongkir' => $ongkir,
                'total' => round($subtotal + $ongkir, $this->kurs->desimal($mataUang)),
                'subtotal_idr' => $subtotalIdr,
                'ongkir_idr' => $ongkirIdr,
                'total_idr' => $subtotalIdr + $ongkirIdr,
                'berat_gram' => $berat,
                'kadaluarsa_pada' => now()->addHours(config('toko.order.batas_bayar_jam')),
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'variant_id' => $item->variant_id,
                    'qty' => $item->qty,
                    'harga_saat_itu' => $item->variant->harga_idr,
                ]);

                $stock = $stocks->get($item->variant_id);
                $stock->update(['jumlah_reserved' => $stock->jumlah_reserved + $item->qty]);

                StockHistory::create([
                    'variant_id' => $item->variant_id,
                    'perubahan' => -$item->qty,
                    'alasan' => 'reserve',
                    'order_id' => $order->id,
                ]);
            }

            $cart->items()->delete();
            $order->statusHistories()->create(['ke' => Order::STATUS_MENUNGGU_PEMBAYARAN, 'user_id' => $user->id]);

            return $order;
        });

        $user->notify(new OrderDibuat($order));
        \App\Support\NotifikasiAdmin::pesananBaru($order);

        return $order;
    }

    private function cekItem(CartItem $item, ?Stock $stock): void
    {
        $nama = $item->variant->product->getTranslation('nama_terjemahan', app()->getLocale());

        if (! $item->variant->product->is_active) {
            throw new TokoException(__(':name is no longer available. Remove it from your cart to continue.', ['name' => $nama]));
        }

        $tersedia = $stock ? $stock->jumlah - $stock->jumlah_reserved : 0;
        if ($item->qty > $tersedia) {
            throw new TokoException($tersedia > 0
                ? __('Only :count left of :name. Update your cart to continue.', ['count' => $tersedia, 'name' => $nama])
                : __(':name just sold out. Remove it from your cart to continue.', ['name' => $nama]));
        }
    }

    private function nomorBaru(): string
    {
        do {
            $nomor = config('toko.order.prefix_nomor').'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::query()->where('nomor', $nomor)->exists());

        return $nomor;
    }
}
