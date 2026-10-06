@extends('layouts.toko')

@section('isi')
    <section class="hero wrap">
        <div class="hero-teks">
            <h1 class="display">{{ \App\Support\Tema::beranda('judul') ?? __('Everyday basics in honest colors.') }}</h1>
            <p class="lead">{{ \App\Support\Tema::beranda('teks') ?? __('Cotton, linen and denim made to be worn often. Ships from Indonesia to Indonesia and Taiwan.') }}</p>
            <a class="tombol" href="{{ route('produk.index') }}">{{ __('Browse the collection') }}</a>
        </div>

        @if ($mozaik->isNotEmpty())
            <div class="mozaik" aria-hidden="true">
                @foreach ($mozaik as $m)
                    <a href="{{ $m['url'] }}" tabindex="-1" class="mozaik-{{ $loop->iteration }}">
                        @if ($m['foto'])<img src="{{ $m['foto'] }}" alt="" width="400" height="400">@endif
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if ($kategori)
        <nav class="wrap kategori" aria-label="{{ __('Categories') }}">
            <h2 class="sr-only">{{ __('Categories') }}</h2>
            <ul>
                @foreach ($kategori as $k)
                    <li><a href="{{ route('produk.index', ['kategori' => $k['slug']]) }}">{{ $k['nama'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <section class="wrap bagian">
        <div class="bagian-kepala">
            <h2>{{ __('New in') }}</h2>
            <a href="{{ route('produk.index') }}">{{ __('See all') }}</a>
        </div>
        <div class="grid">
            @foreach ($produk as $k)
                @include('toko.partials.kartu', ['k' => $k])
            @endforeach
        </div>
    </section>
@endsection
