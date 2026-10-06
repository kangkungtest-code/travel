<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingSewa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Dokumen penyewa (KTP/paspor, SIM) dari disk privat, hanya untuk admin yang boleh melihat pesanan. */
class DokumenSewaController extends Controller
{
    public function __invoke(Request $request, BookingSewa $booking, string $jenis): StreamedResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin?->adalahAdmin() && $admin->hasPermissionTo('order.lihat'), 403);

        $path = $booking->dokumen[$jenis] ?? null;
        $disk = Storage::disk(config('travel.booking.disk_dokumen'));
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, $booking->order?->nomor.'-'.$jenis.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
