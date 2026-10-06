<?php

namespace App\Http\Controllers\Toko;

use App\Exceptions\TokoException;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Support\Keranjang;
use App\Support\Kurs;
use App\Support\TampilanProduk;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KeranjangController extends Controller
{
    public function __construct(private Keranjang $keranjang) {}

    public function index(): View
    {
        $r = $this->keranjang->ringkasan();

        return view('toko.keranjang', [
            'items' => $r['items'],
            'subtotal' => app(Kurs::class)->formatNilai($r['subtotal_nilai'], TampilanProduk::mataUang()),
            'ada_masalah' => $r['ada_masalah'],
        ]);
    }

    public function tambah(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'uuid'],
            'opsi' => ['nullable', 'array'],
            'opsi.*' => ['string', 'max:100'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:'.config('toko.order.maks_qty_per_item')],
        ]);

        $product = Product::query()->where('is_active', true)->with('variants.stocks')->findOrFail($data['product_id']);
        $pilih = $data['opsi'] ?? [];

        // Cocokkan opsi yang dipilih (mis. Warna + Ukuran) ke satu varian; jalan tanpa JavaScript.
        $variant = $product->variants->first(fn ($v) => collect($v->opsi ?? [])->every(fn ($n, $k) => ($pilih[$k] ?? null) === $n)
            && count($pilih) === count($v->opsi ?? []));

        if (! $variant) {
            return back()->withErrors(['keranjang' => __('Choose a color and size first.')]);
        }

        try {
            $this->keranjang->tambah($variant, (int) ($data['qty'] ?? 1));
        } catch (TokoException $e) {
            return back()->withErrors(['keranjang' => $e->getMessage()]);
        }

        return back()->with('ditambahkan', __('Added to your cart.'));
    }

    public function ubah(Request $request, CartItem $item): RedirectResponse
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:'.config('toko.order.maks_qty_per_item')]]);

        try {
            $this->keranjang->ubah($item, (int) $data['qty']);
        } catch (TokoException $e) {
            return back()->withErrors(['keranjang' => $e->getMessage()]);
        }

        return back();
    }

    public function hapus(CartItem $item): RedirectResponse
    {
        $this->keranjang->hapus($item);

        return back()->with('status', __('Removed from your cart.'));
    }
}
