<?php

namespace App\Actions\Retur;

use App\Exceptions\TokoException;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Support\ProductImageStorage;
use App\Support\TampilanRetur;
use Illuminate\Http\UploadedFile;

/** Pembeli mengajukan retur (barang cacat / salah kirim) dari order yang sudah selesai. */
class AjukanReturAction
{
    public function execute(Order $order, string $alasan, ?UploadedFile $foto = null): ReturnRequest
    {
        if (! TampilanRetur::bisaDiajukan($order)) {
            throw new TokoException(__('A return can no longer be requested for this order.'));
        }

        $path = $foto ? app(ProductImageStorage::class)->store($foto, 'retur') : null;

        $retur = $order->returnRequests()->create([
            'alasan' => $alasan,
            'foto_bukti' => $path,
            'status' => ReturnRequest::STATUS_DIAJUKAN,
        ]);

        \App\Support\NotifikasiAdmin::returDiajukan($retur);

        return $retur;
    }
}
