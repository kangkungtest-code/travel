<?php

namespace App\Http\Controllers\Toko;

use App\Http\Controllers\Controller;
use App\Models\HalamanKebijakan;
use App\Models\Product;
use Illuminate\Http\Response;

/** sitemap.xml untuk Google: halaman publik + semua produk aktif (dengan fotonya). */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $produk = Product::query()
            ->where('is_active', true)
            ->with('images')
            ->orderByDesc('updated_at')
            ->get();

        $kategori = \App\Models\Category::query()->tampil()->pluck('slug');

        $halaman = collect([
            ['loc' => route('home'), 'lastmod' => $produk->max('updated_at')],
            ['loc' => route('produk.index'), 'lastmod' => $produk->max('updated_at')],
        ])
            ->merge($kategori->map(fn ($k) => ['loc' => route('produk.index', ['kategori' => $k])]))
            ->merge($produk->map(fn (Product $p) => [
                'loc' => route('produk.show', $p),
                'lastmod' => $p->updated_at,
                'gambar' => $p->images->map->url()->all(),
            ]))
            ->push(['loc' => route('sewa.index')])
            ->merge(\App\Models\TipeKendaraan::query()->tampil()->with('foto')->get()->map(fn (\App\Models\TipeKendaraan $t) => [
                'loc' => route('sewa.show', $t),
                'lastmod' => $t->updated_at,
                'gambar' => $t->foto->map->url()->all(),
            ]))
            ->push(['loc' => route('faq')])
            ->merge(HalamanKebijakan::query()->tampil()->get()->map(fn (HalamanKebijakan $h) => [
                'loc' => route('kebijakan', $h),
                'lastmod' => $h->updated_at,
            ]));

        return response()
            ->view('sitemap', ['halaman' => $halaman], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
