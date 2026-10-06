<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminApi\AdminResource;
use App\Http\Resources\AdminApi\Format;
use App\Models\PerangkatAdmin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Token login berlaku 90 hari; aplikasi meminta login ulang setelahnya. */
    public const UMUR_TOKEN_HARI = 90;

    public function masuk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'nama_perangkat' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], (string) $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 422);
        }
        if (! $user->adalahAdmin()) {
            return response()->json(['message' => 'Akun ini bukan akun admin.'], 403);
        }

        $kadaluarsa = now()->addDays(self::UMUR_TOKEN_HARI);
        $token = $user->createToken('aplikasi-admin: '.$data['nama_perangkat'], ['admin'], $kadaluarsa);

        return response()->json([
            'token' => $token->plainTextToken,
            'kadaluarsa_pada' => Format::waktu($kadaluarsa),
            'admin' => new AdminResource($user),
        ]);
    }

    public function saya(Request $request): AdminResource
    {
        return new AdminResource($request->user());
    }

    /** Keluar: token dicabut dan HP ini berhenti menerima notifikasi push. */
    public function keluar(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();
        PerangkatAdmin::where('personal_access_token_id', $token->id)->delete();
        $token->delete();

        return response()->noContent();
    }
}
