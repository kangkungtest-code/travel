<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Order\BatalkanOrderAction;
use App\Actions\Order\UbahStatusOrderAction;
use App\Exceptions\TokoException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminApi\PesananDetailResource;
use App\Http\Resources\AdminApi\PesananRingkasResource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class PesananController extends Controller
{
    /** Tab di aplikasi -> status di database. "batal" = kadaluarsa + dibatalkan. */
    public const TAB = [
        'perlu_diproses' => [Order::STATUS_DIBAYAR],
        'dikemas' => [Order::STATUS_DIPROSES],
        'dikirim' => [Order::STATUS_DIKIRIM],
        'menunggu_bayar' => [Order::STATUS_MENUNGGU_PEMBAYARAN],
        'selesai' => [Order::STATUS_SELESAI],
        'batal' => [Order::STATUS_KADALUARSA, Order::STATUS_DIBATALKAN],
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'tab' => ['nullable', 'in:'.implode(',', array_keys(self::TAB))],
            'q' => ['nullable', 'string', 'max:100'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $tz = config('toko.zona_waktu');

        $pesanan = Order::query()
            ->with(['user', 'payments'])
            ->withSum('items', 'qty')
            ->when($f['tab'] ?? null, fn (Builder $q, string $tab) => $q->whereIn('status', self::TAB[$tab]))
            ->when($f['q'] ?? null, function (Builder $q, string $kata) {
                $like = '%'.addcslashes(mb_strtolower($kata), '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(nomor) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(CAST(alamat_snapshot AS CHAR)) LIKE ?', [$like])
                    ->orWhereHas('user', fn (Builder $u) => $u->whereRaw('LOWER(email) LIKE ?', [$like])->orWhereRaw('LOWER(nama_lengkap) LIKE ?', [$like])));
            })
            ->when($f['dari'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '>=', Carbon::parse($d, $tz)->startOfDay()->utc()))
            ->when($f['sampai'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '<=', Carbon::parse($d, $tz)->endOfDay()->utc()))
            // Yang perlu dikerjakan duluan: yang paling lama dibayar di atas; tab lain terbaru dulu.
            ->when(($f['tab'] ?? null) === 'perlu_diproses', fn (Builder $q) => $q->oldest('dibayar_pada'), fn (Builder $q) => $q->latest())
            ->paginate($f['per_halaman'] ?? 20)
            ->withQueryString();

        return PesananRingkasResource::collection($pesanan);
    }

    /** Jumlah pesanan per tab, untuk badge di aplikasi. */
    public function jumlah(): JsonResponse
    {
        $perStatus = Order::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return response()->json(collect(self::TAB)->map(
            fn (array $status) => (int) collect($status)->sum(fn ($s) => $perStatus[$s] ?? 0)
        ));
    }

    public function show(Order $pesanan): PesananDetailResource
    {
        return new PesananDetailResource($this->muat($pesanan));
    }

    public function konfirmasiBayar(Request $request, Order $pesanan): PesananDetailResource
    {
        $data = $request->validate(['catatan' => ['required', 'string', 'max:500']]);

        return $this->ubah($request, $pesanan, Order::STATUS_DIBAYAR, $data);
    }

    public function proses(Request $request, Order $pesanan): PesananDetailResource
    {
        return $this->ubah($request, $pesanan, Order::STATUS_DIPROSES);
    }

    public function kirim(Request $request, Order $pesanan): PesananDetailResource
    {
        $data = $request->validate(['resi' => ['required', 'string', 'max:100']]);

        return $this->ubah($request, $pesanan, Order::STATUS_DIKIRIM, $data);
    }

    public function selesai(Request $request, Order $pesanan): PesananDetailResource
    {
        return $this->ubah($request, $pesanan, Order::STATUS_SELESAI);
    }

    public function batalkan(Request $request, Order $pesanan, BatalkanOrderAction $batalkan): PesananDetailResource
    {
        $data = $request->validate(['catatan' => ['required', 'string', 'max:500']]);

        // Sama dengan panel: admin hanya membatalkan order yang belum dibayar.
        if ($pesanan->status !== Order::STATUS_MENUNGGU_PEMBAYARAN) {
            throw new TokoException('Hanya pesanan yang belum dibayar yang bisa dibatalkan.');
        }

        $batalkan->execute($pesanan, $request->user(), $data['catatan']);

        return new PesananDetailResource($this->muat($pesanan->fresh()));
    }

    private function ubah(Request $request, Order $pesanan, string $ke, array $data = []): PesananDetailResource
    {
        app(UbahStatusOrderAction::class)->execute($pesanan, $ke, $request->user(), $data);

        return new PesananDetailResource($this->muat($pesanan->fresh()));
    }

    private function muat(Order $o): Order
    {
        return $o->load([
            'user', 'payments', 'returnRequests', 'statusHistories.user',
            'items.variant.product.images',
        ])->loadSum('items', 'qty');
    }
}
