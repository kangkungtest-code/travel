<?php

namespace App\Http\Controllers\Toko;

use App\Support\GambarOg;
use App\Support\Seo;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Product;
use App\Support\TampilanProduk;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class KatalogController extends Controller
{
    private function query(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->whereHas('variants')
            ->with(['images', 'variants.stocks', 'category'])
            ->withMin('variants', 'harga_idr');
    }

    /** @return array<int, array{slug: string, nama: string}> */
    private function kategori(): array
    {
        return Category::query()->tampil()->get()
            ->map(fn (Category $c) => ['slug' => $c->slug, 'nama' => $c->nama()])
            ->all();
    }

    public function home(): View
    {
        $produk = $this->query()->latest()->take(8)->get();

        return view('toko.home', [
            'produk' => $produk->map(fn (Product $p) => TampilanProduk::kartu($p)),
            'mozaik' => $produk->take(6)->map(fn (Product $p) => TampilanProduk::kartu($p)),
            'kategori' => $this->kategori(),
            'seo' => [
                'gambar' => GambarOg::toko($produk),
                'jsonld' => [Seo::jsonldToko()],
            ],
        ]);
    }

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'kategori' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'urut' => ['nullable', 'in:terbaru,termurah,termahal'],
        ]);

        // Slug kategori yang tidak dikenal / nonaktif diabaikan (tautan lama tetap membuka katalog).
        $kategoriAktif = filled($filter['kategori'] ?? null)
            ? Category::query()->where('is_active', true)->where('slug', $filter['kategori'])->first()
            : null;
        $filter['kategori'] = $kategoriAktif?->slug;

        $query = $this->query()
            ->when($kategoriAktif, fn (Builder $q, Category $k) => $q->where('category_id', $k->id))
            ->when($filter['q'] ?? null, fn (Builder $q, string $s) => Product::cariNama($q, $s));

        match ($filter['urut'] ?? 'terbaru') {
            'termurah' => $query->orderBy('variants_min_harga_idr'),
            'termahal' => $query->orderByDesc('variants_min_harga_idr'),
            default => $query->latest(),
        };

        $produk = $query->paginate(12)->withQueryString();

        return view('toko.produk.index', [
            'produk' => $produk,
            'kartu' => $produk->getCollection()->map(fn (Product $p) => TampilanProduk::kartu($p)),
            'kategori' => $this->kategori(),
            'filter' => array_filter($filter) + ['urut' => 'terbaru'],
            'kategori_aktif' => $kategoriAktif?->nama(),
            'seo' => [
                // Urutan & halaman tidak membuat halaman baru di mata Google; kategori iya.
                'kanonik' => route('produk.index', array_filter([
                    'kategori' => $filter['kategori'] ?? null,
                    'page' => $produk->currentPage() > 1 ? $produk->currentPage() : null,
                ])),
                'noindex' => filled($filter['q'] ?? null) || $produk->isEmpty(),
                'gambar' => GambarOg::toko($produk->getCollection()),
                'deskripsi' => $kategoriAktif
                    ? __(':category from :store. Prices in rupiah, US dollars or Taiwan dollars.', ['category' => $kategoriAktif->nama(), 'store' => config('toko.nama')])
                    : null,
            ],
        ]);
    }

    public function show(Request $request, Product $product): View|\Illuminate\Http\RedirectResponse
    {
        abort_unless($product->is_active, 404);

        // Alamat lama (UUID) -> alamat kanonik berbasis slug.
        if ($request->route()->originalParameter('product') !== $product->slug) {
            return redirect()->route('produk.show', $product, 301);
        }
        $product->load(['images', 'variants.stocks', 'category']);

        $detail = TampilanProduk::detail($product);

        return view('toko.produk.show', [
            'p' => $detail,
            'seo' => [
                'tipe' => 'product',
                'gambar' => GambarOg::produk($product),
                'jsonld' => [Seo::jsonldProduk($product, $detail, $product->images->map->url()->all())],
            ],
            'terkait' => $this->query()
                ->where('id', '!=', $product->id)
                ->when($product->category_id, fn (Builder $q, string $k) => $q->where('category_id', $k))
                ->take(4)->get()
                ->map(fn (Product $x) => TampilanProduk::kartu($x)),
        ]);
    }

    public function kebijakan(\App\Models\HalamanKebijakan $halaman): View
    {
        abort_unless($halaman->is_active, 404);

        return view('toko.kebijakan', [
            'h' => $halaman,
            'lain' => \App\Models\HalamanKebijakan::tautan(),
        ]);
    }

    public function faq(): View
    {
        return view('toko.faq', [
            'faq' => Faq::query()->where('is_active', true)->orderBy('urutan')->get(),
        ]);
    }
}
