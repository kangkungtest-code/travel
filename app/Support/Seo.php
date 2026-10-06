<?php

namespace App\Support;

/**
 * Data <head> untuk mesin pencari & preview tautan (Open Graph).
 * View boleh mengirim $seo = ['deskripsi', 'gambar', 'tipe', 'kanonik', 'noindex', 'jsonld'];
 * sisanya diisi default di sini.
 */
class Seo
{
    /** Halaman yang boleh muncul di Google; sisanya (akun, keranjang, login, ...) noindex. */
    private const BOLEH_DIINDEKS = ['home', 'produk.index', 'produk.show', 'faq', 'kebijakan'];

    public static function untuk(array $seo, ?string $judul): array
    {
        $nama = config('toko.nama');
        $route = request()->route()?->getName();

        $deskripsi = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($seo['deskripsi'] ?? ''))) ?? '');
        if ($deskripsi === '') {
            $deskripsi = __('Everyday basics in honest colors.').' '.__(':store ships clothing from Indonesia.', ['store' => $nama]);
        }

        $noindex = ! self::bolehDiindeks()
            || ($seo['noindex'] ?? false)
            || ! in_array($route, self::BOLEH_DIINDEKS, true);

        return [
            'judul' => $judul ? "{$judul} | {$nama}" : $nama,
            'judul_og' => $judul ?: $nama,
            'deskripsi' => \Illuminate\Support\Str::limit($deskripsi, 160),
            'kanonik' => $seo['kanonik'] ?? url()->current(),
            'gambar' => $seo['gambar'] ?? null,
            'tipe' => $seo['tipe'] ?? 'website',
            'locale' => str_replace('-', '_', self::localeOg(app()->getLocale())),
            'locale_lain' => collect(array_keys(config('toko.locales')))
                ->reject(fn ($l) => $l === app()->getLocale())
                ->map(fn ($l) => self::localeOg($l))->values()->all(),
            'noindex' => $noindex,
            'jsonld' => $seo['jsonld'] ?? [],
        ];
    }

    /** Hanya production yang boleh diindeks; dev/demo berisi data contoh. */
    public static function bolehDiindeks(): bool
    {
        return app()->isProduction();
    }

    private static function localeOg(string $locale): string
    {
        return match ($locale) {
            'id' => 'id_ID',
            'zh_TW' => 'zh_TW',
            default => 'en_US',
        };
    }

    /** JSON-LD Product (schema.org) dengan rentang harga IDR & ketersediaan. */
    public static function jsonldProduk(\App\Models\Product $p, array $detail, array $gambar): array
    {
        $harga = $p->variants->pluck('harga_idr')->map(fn ($h) => (float) $h);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $detail['nama'],
            'description' => $detail['deskripsi'] ? \Illuminate\Support\Str::limit(strip_tags($detail['deskripsi']), 500) : null,
            'image' => $gambar ?: null,
            'category' => $p->category?->nama('en'),
            'brand' => ['@type' => 'Brand', 'name' => config('toko.nama')],
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'IDR',
                'lowPrice' => $harga->min(),
                'highPrice' => $harga->max(),
                'offerCount' => $p->variants->count(),
                'availability' => $detail['habis'] ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
                'url' => route('produk.show', $p),
            ],
        ]);
    }

    public static function jsonldToko(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('toko.nama'),
            'url' => route('home'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('produk.index').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
}
