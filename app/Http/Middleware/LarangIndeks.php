<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server dev/demo berisi data contoh: minta mesin pencari tidak mengindeks
 * apa pun (header berlaku juga untuk sitemap, JSON, dan panel admin).
 * Di production, panel admin tetap tidak diindeks.
 */
class LarangIndeks
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Seo::bolehDiindeks() || $request->is('admin', 'admin/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
