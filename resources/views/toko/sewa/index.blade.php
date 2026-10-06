@extends('layouts.toko', ['judul' => __('Vehicles for rent')])

@section('isi')
    <div class="wrap halaman">
        <h1 class="judul-halaman">{{ __('Vehicles for rent') }}</h1>

        <div class="kotak-cari kotak-cari-baris">
            @include('toko.sewa._cari', ['cari' => $cari, 'jenis' => $jenis, 'tombol' => __('Update')])
        </div>

        <nav class="kategori kategori-rapat" aria-label="{{ __('Vehicle type') }}">
            <ul>
                <li><a href="{{ route('sewa.index', $cari->query()) }}" @if (! $jenis) aria-current="page" @endif>{{ __('All') }}</a></li>
                @foreach (\App\Support\Spesifikasi::pilihan('jenis', app()->getLocale()) as $kode => $label)
                    <li><a href="{{ route('sewa.index', $cari->query() + ['jenis' => $kode]) }}" @if ($jenis === $kode) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        @if ($cari->lengkap())
            <p class="catatan">{{ __(':from → :to at :place', [
                'from' => \App\Support\TampilanKendaraan::waktu($cari->mulai),
                'to' => \App\Support\TampilanKendaraan::waktu($cari->selesai),
                'place' => $cari->lokasi->nama(),
            ]) }}</p>
        @else
            <p class="catatan">{{ __('Choose your dates to see availability and the total price.') }}</p>
        @endif

        @if ($kendaraan->isEmpty())
            <p class="kosong">{{ __('No vehicles match your search. Try another location or rental type.') }}</p>
        @else
            <div class="grid">
                @foreach ($kendaraan as $k)
                    @include('toko.partials.kartu-kendaraan', ['k' => $k])
                @endforeach
            </div>
        @endif
    </div>
@endsection
