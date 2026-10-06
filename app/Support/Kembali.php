<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Halaman tujuan setelah masuk/daftar lewat pop-up: hanya path di situs ini (anti open-redirect). */
class Kembali
{
    public static function simpan(Request $request): void
    {
        $k = (string) $request->input('kembali', '');

        if ($k !== '' && str_starts_with($k, '/') && ! str_starts_with($k, '//') && ! str_contains($k, '\\') && strlen($k) <= 500) {
            $request->session()->put('url.intended', url($k));
        }
    }
}
