<?php

namespace App\Http\Controllers\Toko;

use App\Actions\Order\BatalkanOrderAction;
use App\Actions\Retur\AjukanReturAction;
use App\Actions\Retur\ProsesReturAction;
use App\Exceptions\TokoException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Support\TampilanOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PesananController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()->latest()->paginate(10);

        return view('toko.akun.pesanan', [
            'orders' => $orders,
            'pesanan' => $orders->getCollection()->map(fn (Order $o) => TampilanOrder::ringkas($o)),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->pastikanMilik($request, $order);

        return view('toko.akun.pesanan-detail', ['o' => TampilanOrder::detail($order)]);
    }

    public function batal(Request $request, Order $order, BatalkanOrderAction $batalkan): RedirectResponse
    {
        $this->pastikanMilik($request, $order);

        try {
            $batalkan->execute($order, $request->user(), 'Dibatalkan pembeli');
        } catch (TokoException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('status', __('Order cancelled.'));
    }

    public function ajukanRetur(Request $request, Order $order, AjukanReturAction $ajukan): RedirectResponse
    {
        $this->pastikanMilik($request, $order);
        $data = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:2000'],
            'foto' => ['required', 'image', 'max:'.config('toko.product_images.max_upload_kb')],
        ]);

        try {
            $ajukan->execute($order, $data['alasan'], $request->file('foto'));
        } catch (TokoException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('status', __('Return requested. We will review it within 2 business days.'));
    }

    public function resiRetur(Request $request, ReturnRequest $retur, ProsesReturAction $proses): RedirectResponse
    {
        $this->pastikanMilik($request, $retur->order);
        $data = $request->validate(['resi_kembali' => ['required', 'string', 'max:100']]);

        try {
            $proses->isiResiKembali($retur, $data['resi_kembali']);
        } catch (TokoException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('status', __('Tracking number saved.'));
    }

    private function pastikanMilik(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
