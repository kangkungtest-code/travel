<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API aplikasi admin: wajib token Sanctum berkemampuan `admin` milik user yang masih
 * punya role staff. Role dicabut -> token lama langsung tidak berlaku.
 */
class AdminApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tokenCan('admin') || ! $user->adalahAdmin()) {
            return response()->json(['message' => 'Akun ini tidak punya akses aplikasi admin.'], 403);
        }

        return $next($request);
    }
}
