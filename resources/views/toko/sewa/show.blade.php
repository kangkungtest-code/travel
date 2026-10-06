@extends('layouts.toko', ['judul' => $judul, 'seo' => $seo])

@section('isi')
    <div class="wrap halaman">
        <nav class="remah" aria-label="breadcrumb">
            <a href="{{ route('sewa.index', $cari->query()) }}">{{ __('Vehicles for rent') }}</a>
        </nav>

        <h1 class="judul-produk judul-sewa">{{ $k['nama'] }}</h1>

        <div class="produk produk-sewa">
            <div class="galeri" data-galeri>
                <div class="galeri-utama">
                    @if (count($k['foto']) > 1)
                        <div class="geser geser-besar" data-geser data-galeri-geser role="region" aria-roledescription="carousel" aria-label="{{ __('Vehicle photos') }}">
                            <div class="geser-jalur" data-geser-jalur>
                                @foreach ($k['foto'] as $i => $f)
                                    <img class="geser-slide" src="{{ $f['url'] }}" alt="{{ $i === 0 ? $k['nama'] : $k['nama'].' — '.__('photo :n', ['n' => $i + 1]) }}"
                                         width="1600" height="1600" @if ($i > 0) loading="lazy" @endif draggable="false" data-i="{{ $i }}">
                                @endforeach
                            </div>
                            <button type="button" class="geser-panah geser-kiri" data-geser-sebelum aria-label="{{ __('Previous photo') }}">&#8249;</button>
                            <button type="button" class="geser-panah geser-kanan" data-geser-berikut aria-label="{{ __('Next photo') }}">&#8250;</button>
                            <span class="geser-titik" data-geser-titik aria-hidden="true"></span>
                        </div>
                    @elseif ($k['foto'])
                        <img src="{{ $k['foto'][0]['url'] }}" alt="{{ $k['nama'] }}" width="1600" height="1600">
                    @else
                        <span class="tanpa-foto kendaraan-siluet" aria-hidden="true">
                            <svg viewBox="0 0 64 32" width="240" height="120"><path d="M6 22h52v-6l-8-2-8-7H22l-8 7-8 2z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="17" cy="23" r="4" fill="var(--sage)" stroke="currentColor" stroke-width="1.5"/><circle cx="47" cy="23" r="4" fill="var(--sage)" stroke="currentColor" stroke-width="1.5"/></svg>
                        </span>
                    @endif
                </div>
                @if (count($k['foto']) > 1)
                    <ul class="galeri-thumb" aria-label="{{ __('Vehicle photos') }}">
                        @foreach ($k['foto'] as $i => $f)
                            <li>
                                <button type="button" data-thumb="{{ $i }}" @if ($i === 0) aria-current="true" @endif aria-label="{{ __('Show photo :n', ['n' => $i + 1]) }}">
                                    <img src="{{ $f['thumb'] }}" alt="" width="400" height="400" loading="lazy">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

            </div>

            <div class="info">
                <div class="kotak-cari kotak-cari-kecil">
                    @include('toko.sewa._cari', ['cari' => $cari, 'aksi' => route('sewa.show', $k['slug']), 'tombol' => __('Check price')])
                </div>

                @if (! $adaMode)
                    <p class="pesan pesan-galat">{{ __('This vehicle is not offered for this rental type.') }}</p>
                @elseif ($rincian)
                    <section class="rincian" aria-labelledby="judul-rincian">
                        <h2 id="judul-rincian" class="rincian-judul">{{ __('Price for :duration', ['duration' => $rincian['durasi']]) }}</h2>
                        <dl>
                            @foreach ($rincian['baris'] as [$barisLabel, $barisNilai])
                                <div><dt>{{ $barisLabel }}</dt><dd>{{ $barisNilai }}</dd></div>
                            @endforeach
                            <div class="rincian-total"><dt>{{ __('Total') }}</dt><dd>{{ $rincian['total'] }}</dd></div>
                        </dl>
                        @if ($rincian['minimal'])<p class="catatan">{{ $rincian['minimal'] }}</p>@endif
                        <p class="catatan">{{ $rincian['bbm'] ? __('Driver and fuel included. Tolls and parking are paid on the road.') : ($cari->mode === 'sopir' ? __('Driver included. Fuel, tolls and parking are paid on the road.') : __('Fuel not included. Return the vehicle with the same fuel level.')) }}</p>
                    </section>

                    @if (! $tersedia)
                        <p class="pesan pesan-galat" role="alert">{{ __('Sorry, this vehicle is fully booked for these dates at this location. Try other dates or another vehicle.') }}</p>
                    @elseif (! $pembeli)
                        <p class="catatan">{{ __('Sign in to book. Your search stays as it is.') }}</p>
                        <a class="tombol tombol-lebar" href="{{ route('login') }}" data-masuk>{{ __('Sign in to book') }}</a>
                    @else
                        <form class="form form-booking" method="post" enctype="multipart/form-data" action="{{ route('sewa.pesan', ['kendaraan' => $k['slug']] + $cari->query()) }}">
                            @csrf
                            <h2 class="rincian-judul">{{ __('Renter details') }}</h2>
                            @include('toko.partials.field', ['nama' => 'nama_penyewa', 'label' => __('Renter name'), 'nilai' => $penyewa['nama'], 'attr' => 'required maxlength=150 autocomplete=name'])
                            @include('toko.partials.field', ['nama' => 'telepon', 'label' => __('Phone / WhatsApp'), 'tipe' => 'tel', 'attr' => 'required maxlength=30 autocomplete=tel inputmode=tel'])
                            @if ($perluDokumen)
                                @include('toko.partials.field', ['nama' => 'identitas', 'label' => __('ID card or passport'), 'tipe' => 'file', 'attr' => 'required accept="image/*,.pdf"', 'bantuan' => __('Photo or PDF, max 5 MB. Only our staff can see it.')])
                                @include('toko.partials.field', ['nama' => 'sim', 'label' => __('Driving licence'), 'tipe' => 'file', 'attr' => 'required accept="image/*,.pdf"', 'bantuan' => __('Valid for this vehicle type (international licence for foreign renters).')])
                            @endif
                            @include('toko.partials.field', ['nama' => 'catatan', 'label' => __('Notes (optional)'), 'tipe' => 'textarea', 'attr' => 'maxlength=1000'])
                            @include('toko.partials.setuju', ['kalimat' => 'By booking you agree to our :a and :b.', 'slug' => ['terms', 'returns']])
                            <button type="submit" class="tombol tombol-lebar">{{ __('Book and pay :total', ['total' => $rincian['total']]) }}</button>
                        </form>
                    @endif
                @endif
            </div>

            <section class="spek">
                <h2 class="sr-only">{{ __('Specifications') }}</h2>
                <p class="spek-ringkas">{{ $k['ringkasan'] }}@if ($k['bagasi']) · {{ trans_choice('{1} :count large suitcase|[2,*] :count large suitcases', $k['bagasi']) }}@endif</p>
                @if ($k['fasilitas'])
                    <ul class="fasilitas">
                        @foreach ($k['fasilitas'] as $f)<li>{{ $f }}</li>@endforeach
                    </ul>
                @endif
                @if ($k['deskripsi'])
                    <div class="deskripsi">{!! nl2br(e($k['deskripsi'])) !!}</div>
                @endif
                <table class="tabel-tarif">
                    <caption class="sr-only">{{ __('Rates') }}</caption>
                    <thead><tr><th scope="col">{{ __('Rental type') }}</th><th scope="col">{{ __('24 hours') }}</th><th scope="col">{{ __('12 hours') }}</th></tr></thead>
                    <tbody>
                        @foreach ($k['tarif'] as $t)
                            <tr>
                                <th scope="row">{{ $t['mode'] }}@if ($t['bbm'])<span class="kartu-meta"> · {{ __('fuel included') }}</span>@endif</th>
                                <td>{{ $t['harian'] }}</td>
                                <td>{{ $t['jam12'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>
    </div>
@endsection
