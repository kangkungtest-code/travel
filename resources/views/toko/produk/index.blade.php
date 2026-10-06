@extends('layouts.toko', ['judul' => $kategori_aktif ?? __('All products')])

@section('isi')
    <div class="wrap halaman">
        <h1 class="judul-halaman">{{ $kategori_aktif ?? __('All products') }}</h1>

        <div class="toolbar">
            <ul class="kategori kategori-rapat" aria-label="{{ __('Categories') }}">
                <li><a href="{{ route('produk.index', array_filter(['q' => $filter['q'] ?? null, 'urut' => $filter['urut'] !== 'terbaru' ? $filter['urut'] : null])) }}" @if (empty($filter['kategori'])) aria-current="page" @endif>{{ __('All') }}</a></li>
                @foreach ($kategori as $k)
                    <li><a href="{{ route('produk.index', array_filter(['kategori' => $k['slug'], 'q' => $filter['q'] ?? null, 'urut' => $filter['urut'] !== 'terbaru' ? $filter['urut'] : null])) }}" @if (($filter['kategori'] ?? null) === $k['slug']) aria-current="page" @endif>{{ $k['nama'] }}</a></li>
                @endforeach
            </ul>

            <form class="cari" method="get" action="{{ route('produk.index') }}" role="search">
                @if (! empty($filter['kategori']))<input type="hidden" name="kategori" value="{{ $filter['kategori'] }}">@endif
                <label class="sr-only" for="q">{{ __('Search products') }}</label>
                <input id="q" type="search" name="q" value="{{ $filter['q'] ?? '' }}" placeholder="{{ __('Search products') }}">
                <label class="sr-only" for="urut">{{ __('Sort') }}</label>
                <select id="urut" name="urut" data-auto-submit-field>
                    <option value="terbaru" @selected($filter['urut'] === 'terbaru')>{{ __('Newest') }}</option>
                    <option value="termurah" @selected($filter['urut'] === 'termurah')>{{ __('Price: low to high') }}</option>
                    <option value="termahal" @selected($filter['urut'] === 'termahal')>{{ __('Price: high to low') }}</option>
                </select>
                <button type="submit" class="tombol tombol-kecil">{{ __('Search') }}</button>
            </form>
        </div>

        @if ($kartu->isEmpty())
            <div class="kosong">
                <p>{{ __('No products match your search.') }}</p>
                <a class="tombol" href="{{ route('produk.index') }}">{{ __('Clear filters') }}</a>
            </div>
        @else
            <div class="grid">
                @foreach ($kartu as $k)
                    @include('toko.partials.kartu', ['k' => $k])
                @endforeach
            </div>

            @if ($produk->hasPages())
                <nav class="paginasi" aria-label="{{ __('Page :current of :last', ['current' => $produk->currentPage(), 'last' => $produk->lastPage()]) }}">
                    @if ($produk->onFirstPage())
                        <span aria-disabled="true">{{ __('Previous') }}</span>
                    @else
                        <a href="{{ $produk->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>
                    @endif
                    <span>{{ __('Page :current of :last', ['current' => $produk->currentPage(), 'last' => $produk->lastPage()]) }}</span>
                    @if ($produk->hasMorePages())
                        <a href="{{ $produk->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
                    @else
                        <span aria-disabled="true">{{ __('Next') }}</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
@endsection
