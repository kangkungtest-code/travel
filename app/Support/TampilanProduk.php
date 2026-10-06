<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Menyiapkan data produk untuk view storefront: teks sesuai bahasa aktif,
 * harga sesuai mata uang aktif, opsi varian terurut.
 */
class TampilanProduk
{
    /** Urutan opsi yang umum; opsi lain menyusul sesuai abjad. */
    private const URUTAN_OPSI = ['Warna', 'Ukuran'];

    private const URUTAN_UKURAN = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', 'All Size'];

    public static function mataUang(): string
    {
        return app()->bound('toko.currency') ? app('toko.currency') : 'USD';
    }

    public static function harga(float $idr): string
    {
        return app(Kurs::class)->format($idr, self::mataUang());
    }

    /** Label opsi/nilai lewat kamus UI (mis. "Hitam" -> "Black"); kalau tidak ada, apa adanya. */
    public static function label(string $teks): string
    {
        return __($teks);
    }

    public static function kartu(Product $p): array
    {
        $harga = $p->variants->pluck('harga_idr')->map(fn ($h) => (float) $h);
        $opsi = self::opsi($p->variants);

        return [
            'url' => route('produk.show', $p),
            'nama' => $p->getTranslation('nama_terjemahan', app()->getLocale()),
            'kategori' => $p->category?->nama(),
            'foto' => $p->images->first()?->thumbUrl(),
            // Untuk carousel kartu: maks 4 foto, cukup thumbnail 400px.
            'foto_semua' => $p->images->take(4)->map(fn (ProductImage $i) => $i->thumbUrl())->values()->all(),
            'harga' => self::harga($harga->min() ?? 0),
            'mulai_dari' => $harga->unique()->count() > 1,
            'ringkas_opsi' => $opsi->map(fn (array $o) => trans_choice(':count '.$o['kunci'], count($o['nilai'])))->values()->all(),
            'habis' => $p->variants->every(fn (ProductVariant $v) => $v->stokTersedia() <= 0),
        ];
    }

    public static function detail(Product $p): array
    {
        $locale = app()->getLocale();

        $varian = $p->variants
            ->sortBy(fn (ProductVariant $v) => self::kunciUrut($v))
            ->values()
            ->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'opsi' => (object) ($v->opsi ?? []),
                'harga' => self::harga((float) $v->harga_idr),
                'stok' => $v->stokTersedia(),
            ]);

        $awal = $varian->firstWhere(fn ($v) => $v['stok'] > 0) ?? $varian->first();

        // Foto warna varian awal ditaruh paling depan supaya cocok dengan pilihan yang tercentang.
        $warnaAwal = $awal ? ($awal['opsi']->{Product::OPSI_WARNA} ?? null) : null;
        $foto = $p->images
            ->sortBy(fn (ProductImage $i) => [$warnaAwal && $i->warna === $warnaAwal ? 0 : 1, $i->urutan])
            ->values()
            ->map(fn (ProductImage $i) => ['url' => $i->url(), 'thumb' => $i->thumbUrl(), 'warna' => $i->warna]);

        return [
            'id' => $p->id,
            'nama' => $p->getTranslation('nama_terjemahan', $locale),
            'deskripsi' => $p->getTranslation('deskripsi_terjemahan', $locale),
            'kategori' => $p->category ? ['slug' => $p->category->slug, 'nama' => $p->category->nama()] : null,
            'foto' => $foto->all(),
            'opsi_warna' => Product::OPSI_WARNA,
            'opsi' => self::opsi($p->variants)->values()->all(),
            'varian' => $varian->all(),
            'awal' => $awal,
            'habis' => $varian->every(fn ($v) => $v['stok'] <= 0),
        ];
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, array{kunci: string, label: string, nilai: array<int, array{nilai: string, label: string}>}>
     */
    public static function opsi(Collection $variants): Collection
    {
        $grup = [];
        foreach ($variants as $v) {
            foreach (($v->opsi ?? []) as $kunci => $nilai) {
                $grup[$kunci][$nilai] = true;
            }
        }

        return collect($grup)
            ->sortBy(fn ($_, string $kunci) => [array_search($kunci, self::URUTAN_OPSI, true) === false ? 99 : array_search($kunci, self::URUTAN_OPSI, true), $kunci])
            ->map(fn (array $nilai, string $kunci) => [
                'kunci' => $kunci,
                'label' => self::label($kunci),
                'nilai' => collect(array_keys($nilai))
                    ->sortBy(fn (string $n) => self::urutNilai($n))
                    ->values()
                    ->map(fn (string $n) => ['nilai' => $n, 'label' => self::label($n)])
                    ->all(),
            ]);
    }

    private static function urutNilai(string $nilai): string
    {
        $i = array_search($nilai, self::URUTAN_UKURAN, true);
        if ($i !== false) {
            return sprintf('0-%03d', $i);
        }

        return is_numeric($nilai) ? sprintf('1-%08.2f', $nilai) : '2-'.mb_strtolower(self::label($nilai));
    }

    private static function kunciUrut(ProductVariant $v): string
    {
        return collect($v->opsi ?? [])
            ->sortBy(fn ($_, string $k) => array_search($k, self::URUTAN_OPSI, true) === false ? 99 : array_search($k, self::URUTAN_OPSI, true))
            ->map(fn (string $n) => self::urutNilai($n))
            ->implode('|');
    }
}
