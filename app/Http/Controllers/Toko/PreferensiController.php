<?php

namespace App\Http\Controllers\Toko;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferensiController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['nullable', Rule::in(array_keys(config('toko.locales')))],
            'currency' => ['nullable', Rule::in(config('toko.currencies'))],
        ]);

        $simpan = [];
        if (! empty($data['locale'])) {
            $request->session()->put('locale', $data['locale']);
            $simpan['bahasa_preferensi'] = $data['locale'];
        }
        if (! empty($data['currency'])) {
            $request->session()->put('currency', $data['currency']);
            $simpan['mata_uang_preferensi'] = $data['currency'];
        }

        if ($simpan && $request->user()) {
            $request->user()->update($simpan);
        }

        return redirect()->back(fallback: route('home'));
    }
}
