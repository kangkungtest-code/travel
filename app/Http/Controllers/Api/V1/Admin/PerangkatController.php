<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PerangkatAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Pendaftaran token FCM HP admin (dipanggil setelah login & tiap token FCM berganti). */
class PerangkatController extends Controller
{
    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'in:android,ios'],
        ]);

        // Satu token FCM = satu HP; kalau HP dipakai akun lain, pindah ke akun yang sedang login.
        PerangkatAdmin::updateOrCreate(['fcm_token' => $data['fcm_token']], [
            'user_id' => $request->user()->id,
            'personal_access_token_id' => $request->user()->currentAccessToken()->id,
            'platform' => $data['platform'] ?? 'android',
            'terakhir_aktif' => now(),
        ]);

        return response()->json(['message' => 'Perangkat terdaftar untuk notifikasi.']);
    }

    public function hapus(Request $request): Response
    {
        $data = $request->validate(['fcm_token' => ['required', 'string', 'max:512']]);

        $request->user()->perangkatAdmin()->where('fcm_token', $data['fcm_token'])->delete();

        return response()->noContent();
    }
}
