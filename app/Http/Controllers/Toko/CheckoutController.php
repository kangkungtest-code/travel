<?php

namespace App\Http\Controllers\Toko;

use App\Actions\Order\BuatOrderAction;
use App\Exceptions\TokoException;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Support\HitungOngkir;
use App\Support\Keranjang;
use App\Support\Kurs;
use App\Support\TampilanProduk;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function show(Request $request, Keranjang $keranjang, HitungOngkir $hitungOngkir): View|RedirectResponse
    {
        $r = $keranjang->ringkasan();
        if ($r['items']->isEmpty()) {
            return redirect()->route('keranjang');
        }

        $semuaAlamat = $request->user()->addresses()->orderByDesc('is_default')->oldest()->get();
        $terpilih = $semuaAlamat->firstWhere('id', $request->query('alamat')) ?? $semuaAlamat->first();
        $ongkirIdr = $terpilih ? $hitungOngkir->hitung($terpilih->negara, $r['berat_gram']) : null;
        $kurs = app(Kurs::class);
        $mu = $kurs->mataUangEfektif(TampilanProduk::mataUang());
        $ongkirNilai = $ongkirIdr !== null ? $kurs->konversi($ongkirIdr, $mu) : null;

        return view('toko.checkout', [
            'items' => $r['items'],
            'ada_masalah' => $r['ada_masalah'],
            'alamat' => $semuaAlamat,
            'terpilih' => $terpilih,
            'subtotal' => $kurs->formatNilai($r['subtotal_nilai'], $mu),
            'berat' => $r['berat_gram'],
            'ongkir' => $ongkirNilai !== null ? $kurs->formatNilai($ongkirNilai, $mu) : null,
            'total' => $ongkirNilai !== null ? $kurs->formatNilai($r['subtotal_nilai'] + $ongkirNilai, $mu) : null,
            'batasJam' => config('toko.order.batas_bayar_jam'),
        ]);
    }

    public function store(Request $request, BuatOrderAction $buatOrder): RedirectResponse
    {
        $data = $request->validate(['address_id' => ['required', 'uuid']]);
        $alamat = Address::query()->where('user_id', $request->user()->id)->find($data['address_id']);

        if (! $alamat) {
            return back()->withErrors(['checkout' => __('Choose one of your saved addresses.')]);
        }

        try {
            $order = $buatOrder->execute($request->user(), $alamat, app('toko.currency'));
        } catch (TokoException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        return redirect()->route('akun.pesanan.show', $order)->with('status', __('Order placed. Complete the payment before the deadline.'));
    }
}
