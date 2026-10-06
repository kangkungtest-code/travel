<?php

namespace App\Http\Middleware;

use App\Support\VerifikasiEmail;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Checkout & bayar butuh email terverifikasi (kalau verifikasi sedang diwajibkan). */
class EmailTerverifikasi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && VerifikasiEmail::wajib() && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => __('Please verify your email address first.')], 403)
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
