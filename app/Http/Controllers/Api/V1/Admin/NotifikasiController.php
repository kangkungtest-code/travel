<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminApi\NotifikasiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Kotak masuk notifikasi admin (salinan semua push, tetap ada walau Firebase belum aktif). */
class NotifikasiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate(['belum_dibaca' => ['nullable', 'boolean']]);
        $user = $request->user();

        $notifikasi = ($f['belum_dibaca'] ?? false ? $user->unreadNotifications() : $user->notifications())
            ->paginate(30)
            ->withQueryString();

        return NotifikasiResource::collection($notifikasi)
            ->additional(['belum_dibaca' => $user->unreadNotifications()->count()]);
    }

    public function dibaca(Request $request, string $id): JsonResponse
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return response()->json(['belum_dibaca' => $request->user()->unreadNotifications()->count()]);
    }

    public function dibacaSemua(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['belum_dibaca' => 0]);
    }
}
