<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `izin:order.lihat` — permission yang sama dengan panel admin web. */
class IzinApi
{
    public function handle(Request $request, Closure $next, string $izin): Response
    {
        if (! $request->user()?->hasPermissionTo($izin)) {
            return response()->json(['message' => "Butuh izin {$izin}.", 'izin' => $izin], 403);
        }

        return $next($request);
    }
}
