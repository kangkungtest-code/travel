<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Pembayaran\RefundPembayaranAction;
use App\Actions\Retur\ProsesReturAction;
use App\Exceptions\TokoException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminApi\ReturResource;
use App\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReturController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'status' => ['nullable', 'in:diajukan,disetujui,ditolak,selesai'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return ReturResource::collection(
            ReturnRequest::query()
                ->with('order.user')
                ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->latest()
                ->paginate($f['per_halaman'] ?? 20)
                ->withQueryString()
        );
    }

    public function show(ReturnRequest $retur): ReturResource
    {
        return new ReturResource($retur->load('order.user'));
    }

    public function setujui(Request $request, ReturnRequest $retur, ProsesReturAction $proses): ReturResource
    {
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:1000']]);

        return new ReturResource($proses->setujui($retur, $data['catatan'] ?? null)->load('order.user'));
    }

    public function tolak(Request $request, ReturnRequest $retur, ProsesReturAction $proses): ReturResource
    {
        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']]);

        return new ReturResource($proses->tolak($retur, $data['catatan'])->load('order.user'));
    }

    /** Barang retur sudah diterima. Refund dicoba otomatis lewat payment gateway (sama dengan panel). */
    public function selesaikan(Request $request, ReturnRequest $retur, ProsesReturAction $proses): JsonResponse
    {
        $data = $request->validate([
            'penyelesaian' => ['required', 'in:'.implode(',', ReturnRequest::PENYELESAIAN)],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $retur = $proses->selesaikan($retur, $data['penyelesaian'], $data['catatan'] ?? null);

        $refund = null;
        if ($data['penyelesaian'] === 'refund') {
            try {
                $pay = app(RefundPembayaranAction::class)->execute($retur->order, 'Retur: '.$retur->alasan);
                $refund = ['otomatis' => true, 'pesan' => 'Refund diproses lewat payment gateway.', 'refund_id' => $pay->refund_id];
            } catch (TokoException $e) {
                $refund = ['otomatis' => false, 'pesan' => 'Refund manual diperlukan: '.$e->getMessage(), 'refund_id' => null];
            }
        }

        return (new ReturResource($retur->load('order.user')))
            ->additional(['refund' => $refund])
            ->response();
    }
}
