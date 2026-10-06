<?php

namespace App\Support;

use App\Exceptions\TokoException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Keranjang pembeli yang sedang aktif: milik user (guard web) atau tamu (id di sesi).
 * Stok tidak di-reserve di keranjang — reserve baru terjadi saat order dibuat.
 */
class Keranjang
{
    private const SESI = 'cart_id';

    private function user(): ?User
    {
        return Auth::guard('web')->user();
    }

    public function cart(bool $buat = false): ?Cart
    {
        if ($user = $this->user()) {
            return $buat ? Cart::firstOrCreate(['user_id' => $user->id]) : $user->cart;
        }

        $id = session(self::SESI);
        $cart = $id ? Cart::query()->whereNull('user_id')->find($id) : null;

        if (! $cart && $buat) {
            $cart = Cart::create();
            session([self::SESI => $cart->id]);
        }

        return $cart;
    }

    public function jumlahBarang(): int
    {
        return (int) ($this->cart()?->items()->sum('qty') ?? 0);
    }

    public function tambah(ProductVariant $variant, int $qty): CartItem
    {
        $variant->loadMissing('product', 'stocks');
        if (! $variant->product?->is_active) {
            throw new TokoException(__('This product is no longer available.'));
        }

        $cart = $this->cart(buat: true);
        $item = $cart->items()->firstOrNew(['variant_id' => $variant->id]);
        $total = ($item->exists ? $item->qty : 0) + $qty;

        $this->cekQty($variant, $total);

        $item->qty = $total;
        $item->save();
        $cart->touch();

        return $item;
    }

    public function ubah(CartItem $item, int $qty): void
    {
        $this->pastikanMilik($item);

        if ($qty <= 0) {
            $item->delete();

            return;
        }

        $this->cekQty($item->variant()->with('stocks')->firstOrFail(), $qty);
        $item->update(['qty' => $qty]);
    }

    public function hapus(CartItem $item): void
    {
        $this->pastikanMilik($item);
        $item->delete();
    }

    /** Pindahkan isi keranjang tamu ke keranjang user setelah login/daftar. */
    public function gabungkanKe(User $user): void
    {
        $id = session()->pull(self::SESI);
        $tamu = $id ? Cart::query()->whereNull('user_id')->with('items.variant.stocks')->find($id) : null;
        if (! $tamu) {
            return;
        }

        DB::transaction(function () use ($tamu, $user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            foreach ($tamu->items as $itemTamu) {
                $item = $cart->items()->firstOrNew(['variant_id' => $itemTamu->variant_id]);
                $batas = min(config('toko.order.maks_qty_per_item'), max(0, $itemTamu->variant->stokTersedia()));
                $qty = min(($item->exists ? $item->qty : 0) + $itemTamu->qty, $batas);

                if ($qty > 0) {
                    $item->qty = $qty;
                    $item->save();
                }
            }

            $tamu->delete();
        });
    }

    /**
     * Isi keranjang siap tampil/checkout.
     *
     * @return array{items: \Illuminate\Support\Collection<int, array>, subtotal_idr: float, berat_gram: int, ada_masalah: bool}
     */
    public function ringkasan(): array
    {
        $cart = $this->cart();
        $items = $cart
            ? $cart->items()->with(['variant.product.images', 'variant.stocks'])->get()
            : collect();

        $baris = $items->map(function (CartItem $i) {
            $v = $i->variant;
            $tersedia = $v->stokTersedia();
            $masalah = match (true) {
                ! $v->product->is_active => __('This product is no longer available.'),
                $tersedia <= 0 => __('Sold out'),
                $i->qty > $tersedia => __('Only :count left', ['count' => $tersedia]),
                default => null,
            };

            return [
                'item' => $i,
                'variant' => $v,
                'nama' => $v->product->getTranslation('nama_terjemahan', app()->getLocale()),
                'opsi' => collect($v->opsi ?? [])->map(fn ($n, $k) => __($k).': '.__($n))->values()->implode(', '),
                'foto' => $v->product->fotoUntuk($v->opsi[\App\Models\Product::OPSI_WARNA] ?? null)?->thumbUrl(),
                'url' => route('produk.show', $v->product),
                'harga_idr' => (float) $v->harga_idr,
                'total_idr' => (float) $v->harga_idr * $i->qty,
                'tersedia' => $tersedia,
                'masalah' => $masalah,
            ];
        });

        // Nilai tampilan dihitung per item (harga satuan dikonversi lalu dikali qty),
        // sama persis dengan cara BuatOrderAction menghitung total order.
        $kurs = app(Kurs::class);
        $mu = TampilanProduk::mataUang();
        $baris = $baris->map(fn (array $b) => $b + [
            'harga_tampil' => $kurs->format($b['harga_idr'], $mu),
            'total_nilai' => $kurs->konversi($b['harga_idr'], $mu) * $b['item']->qty,
        ])->map(fn (array $b) => $b + ['total_tampil' => $kurs->formatNilai($b['total_nilai'], $kurs->mataUangEfektif($mu))]);

        return [
            'items' => $baris,
            'subtotal_nilai' => (float) $baris->sum('total_nilai'),
            'subtotal_idr' => (float) $baris->sum('total_idr'),
            'berat_gram' => (int) $items->sum(fn (CartItem $i) => $i->variant->berat_gram * $i->qty),
            'ada_masalah' => $baris->contains(fn ($b) => $b['masalah'] !== null),
        ];
    }

    public function kosongkan(Cart $cart): void
    {
        $cart->items()->delete();
    }

    private function cekQty(ProductVariant $variant, int $qty): void
    {
        $maks = config('toko.order.maks_qty_per_item');
        if ($qty > $maks) {
            throw new TokoException(__('You can order at most :count of each item.', ['count' => $maks]));
        }

        $tersedia = $variant->stokTersedia();
        if ($tersedia <= 0) {
            throw new TokoException(__('Sorry, this item is sold out.'));
        }
        if ($qty > $tersedia) {
            throw new TokoException(__('Only :count left', ['count' => $tersedia]));
        }
    }

    private function pastikanMilik(CartItem $item): void
    {
        abort_unless($this->cart()?->id === $item->cart_id, 404);
    }
}
