<?php

namespace App\Http\Middleware;

use App\Support\Fitur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route milik fitur yang sedang dimatikan = 404 (seolah tidak ada). Pemakaian: ->middleware('fitur:retur') */
class FiturAktif
{
    public function handle(Request $request, Closure $next, string $kunci): Response
    {
        abort_unless(Fitur::aktif($kunci), 404);

        return $next($request);
    }
}
