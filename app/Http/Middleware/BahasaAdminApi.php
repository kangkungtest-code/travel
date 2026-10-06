<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Aplikasi admin berbahasa Indonesia: pesan validasi & error bisnis ikut bahasa Indonesia. */
class BahasaAdminApi
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('id');

        return $next($request);
    }
}
