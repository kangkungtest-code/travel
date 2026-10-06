@extends('layouts.toko', ['judul' => $p['nama'], 'seo' => $seo + ['deskripsi' => $p['deskripsi']]])

@section('isi')
    <div class="wrap halaman">
        <nav class="remah" aria-label="breadcrumb">
            <a href="{{ route('produk.index') }}">{{ __('Shop') }}</a>
            @if ($p['kategori'])
                <span aria-hidden="true">/</span>
                <a href="{{ route('produk.index', ['kategori' => $p['kategori']['slug']]) }}">{{ $p['kategori']['nama'] }}</a>
            @endif
        </nav>

        <div class="produk">
            <div class="galeri" data-galeri data-opsi-warna="{{ $p['opsi_warna'] }}">
                <div class="galeri-utama">
                    @if (count($p['foto']) > 1)
                        <div class="geser geser-besar" data-geser data-galeri-geser role="region" aria-roledescription="carousel" aria-label="{{ __('Product photos') }}">
                            <div class="geser-jalur" data-geser-jalur>
                                @foreach ($p['foto'] as $i => $f)
                                    <img class="geser-slide" src="{{ $f['url'] }}" alt="{{ $i === 0 ? $p['nama'] : $p['nama'].' — '.__('photo :n', ['n' => $i + 1]) }}"
                                         width="1600" height="1600" @if ($i > 0) loading="lazy" @endif draggable="false"
                                         data-i="{{ $i }}" @if ($f['warna']) data-warna="{{ $f['warna'] }}" @endif>
                                @endforeach
                            </div>
                            <button type="button" class="geser-panah geser-kiri" data-geser-sebelum aria-label="{{ __('Previous photo') }}">&#8249;</button>
                            <button type="button" class="geser-panah geser-kanan" data-geser-berikut aria-label="{{ __('Next photo') }}">&#8250;</button>
                            <span class="geser-titik" data-geser-titik aria-hidden="true"></span>
                        </div>
                    @elseif ($p['foto'])
                        <img src="{{ $p['foto'][0]['url'] }}" alt="{{ $p['nama'] }}" width="1600" height="1600">
                    @else
                        <span class="tanpa-foto">{{ __('Photo coming soon') }}</span>
                    @endif
                </div>
                @if (count($p['foto']) > 1)
                    <ul class="galeri-thumb" aria-label="{{ __('Product photos') }}">
                        @foreach ($p['foto'] as $i => $f)
                            <li @if ($f['warna']) data-warna="{{ $f['warna'] }}" @endif>
                                <button type="button" data-thumb="{{ $i }}" @if ($i === 0) aria-current="true" @endif aria-label="{{ __('Show photo :n', ['n' => $i + 1]) }}">
                                    <img src="{{ $f['thumb'] }}" alt="" width="400" height="400" loading="lazy">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <form class="info" data-pemilih method="post" action="{{ route('keranjang.tambah') }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $p['id'] }}">
                <h1 class="judul-produk">{{ $p['nama'] }}</h1>
                <p class="harga" data-harga>{{ $p['awal']['harga'] ?? '' }}</p>

                @foreach ($p['opsi'] as $o)
                    <fieldset class="opsi">
                        <legend>{{ $o['label'] }}<span class="opsi-terpilih" data-terpilih="{{ $o['kunci'] }}"></span></legend>
                        <div class="opsi-pilihan">
                            @foreach ($o['nilai'] as $n)
                                <label class="chip">
                                    <input type="radio" name="opsi[{{ $o['kunci'] }}]" value="{{ $n['nilai'] }}" data-label="{{ $n['label'] }}"
                                        @checked(($p['awal']['opsi']->{$o['kunci']} ?? null) === $n['nilai'])>
                                    <span>{{ $n['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <p class="stok" data-stok
                   data-t-habis="{{ __('Sold out') }}"
                   data-t-ada="{{ __('In stock') }}"
                   data-t-sisa="{{ __('Only :count left') }}"
                   data-t-tidak-ada="{{ __('This combination is not available') }}"></p>

                <div class="beli">
                    <label class="qty">
                        <span class="sr-only">{{ __('Quantity') }}</span>
                        <input type="number" name="qty" value="1" min="1" max="{{ config('toko.order.maks_qty_per_item') }}" inputmode="numeric">
                    </label>
                    <button type="submit" class="tombol" data-tombol-beli @disabled($p['habis'])>{{ __('Add to cart') }}</button>
                </div>
                @if (session('ditambahkan'))
                    <p class="catatan berhasil" role="status">{{ session('ditambahkan') }} <a href="{{ route('keranjang') }}">{{ __('View cart') }}</a></p>
                @endif

                @if ($p['deskripsi'])
                    <div class="deskripsi">{!! nl2br(e($p['deskripsi'])) !!}</div>
                @endif
                <p class="sku" data-sku>{{ $p['awal']['sku'] ?? '' }}</p>

                <script type="application/json" data-varian>@json($p['varian'])</script>
            </form>
        </div>

        @if ($terkait->isNotEmpty())
            <section class="bagian">
                <div class="bagian-kepala"><h2>{{ __('You might also like') }}</h2></div>
                <div class="grid">
                    @foreach ($terkait as $k)
                        @include('toko.partials.kartu', ['k' => $k])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
