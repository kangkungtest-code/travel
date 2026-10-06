<?php

namespace App\Http\Middleware;

use App\Support\Kurs;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa & mata uang storefront: sesi > preferensi user (kalau login) > default (en / USD).
 */
class SetPreferensiToko
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locales = array_keys(config('toko.locales'));

        $locale = $request->session()->get('locale', $user?->bahasa_preferensi);
        if (! in_array($locale, $locales, true)) {
            $locale = $locales[0];
        }

        $currency = $request->session()->get('currency', $user?->mata_uang_preferensi);
        if (! in_array($currency, config('toko.currencies'), true)) {
            $currency = in_array('USD', config('toko.currencies'), true) ? 'USD' : config('toko.currencies')[0];
        }

        app()->setLocale($locale);
        app()->instance('toko.currency', app(Kurs::class)->mataUangEfektif($currency));

        return $next($request);
    }
}
