@extends('layouts.toko')

@section('isi')
    <section class="hero hero-sewa wrap">
        <div class="hero-teks">
            <h1 class="display">{{ \App\Support\Tema::beranda('judul') ?? __('Rent a vehicle, with or without a driver') }}</h1>
            <p class="lead">{{ \App\Support\Tema::beranda('teks') ?? __('Pick your dates, choose a vehicle, pay online.') }}</p>
        </div>
        <div class="kotak-cari">
            @include('toko.sewa._cari', ['cari' => $cari])
        </div>
    </section>

    @if ($kendaraan->isNotEmpty())
        <section class="wrap bagian">
            <div class="bagian-kepala">
                <h2>{{ __('Our fleet') }}</h2>
                <a href="{{ route('sewa.index') }}">{{ __('See all') }}</a>
            </div>
            <div class="grid">
                @foreach ($kendaraan as $k)
                    @include('toko.partials.kartu-kendaraan', ['k' => $k])
                @endforeach
            </div>
        </section>
    @endif
@endsection
