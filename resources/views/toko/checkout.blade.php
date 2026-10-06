@extends('layouts.toko', ['judul' => __('Checkout')])

@section('isi')
    <div class="wrap halaman">
        <h1 class="judul-halaman">{{ __('Checkout') }}</h1>

        <div class="dua-kolom">
            <div>
                <section class="blok">
                    <div class="blok-kepala">
                        <h2>{{ __('Ship to') }}</h2>
                        <a href="{{ route('akun.alamat.create', ['kembali' => 'checkout']) }}">{{ __('Add address') }}</a>
                    </div>

                    @if ($alamat->isEmpty())
                        <p>{{ __('Add a shipping address to continue.') }}</p>
                        <a class="tombol" href="{{ route('akun.alamat.create', ['kembali' => 'checkout']) }}">{{ __('Add address') }}</a>
                    @else
                        <form method="get" action="{{ route('checkout') }}" class="pilih-alamat">
                            @foreach ($alamat as $a)
                                <label @class(['kartu-alamat', 'dipilih' => $terpilih?->id === $a->id])>
                                    <input type="radio" name="alamat" value="{{ $a->id }}" @checked($terpilih?->id === $a->id) data-auto-submit-field>
                                    <span>
                                        <strong>{{ $a->label }}</strong><br>{{ $a->nama_penerima }}<br>
                                        {{ $a->detail_alamat }}, {{ $a->kota }} {{ $a->kode_pos }}, {{ __(config('toko.negara.'.$a->negara, $a->negara)) }}<br>
                                        <span class="redup">{{ $a->telepon }}</span>
                                    </span>
                                </label>
                            @endforeach
                            <noscript><button type="submit" class="tombol tombol-kecil">{{ __('Use this address') }}</button></noscript>
                        </form>
                    @endif
                </section>

                <section class="blok">
                    <h2>{{ __('Items') }}</h2>
                    <ul class="daftar-barang">
                        @foreach ($items as $b)
                            @include('toko.partials.baris-barang', ['b' => $b])
                        @endforeach
                    </ul>
                    <p class="redup">{{ __('Total weight: :kg kg', ['kg' => number_format($berat / 1000, 2)]) }}</p>
                </section>
            </div>

            <aside class="ringkasan">
                <dl>
                    <div><dt>{{ __('Subtotal') }}</dt><dd>{{ $subtotal }}</dd></div>
                    <div><dt>{{ __('Shipping') }}</dt><dd>{{ $ongkir ?? '—' }}</dd></div>
                    <div class="ringkasan-total"><dt>{{ __('Total') }}</dt><dd>{{ $total ?? '—' }}</dd></div>
                </dl>

                @if ($terpilih && $ongkir === null)
                    <p class="habis">{{ __('We can\'t ship to this address yet.') }}</p>
                @endif

                <form method="post" action="{{ route('checkout') }}">
                    @csrf
                    <input type="hidden" name="address_id" value="{{ $terpilih?->id }}">
                    <button type="submit" class="tombol tombol-lebar" @disabled(! $terpilih || $ongkir === null || $ada_masalah)>{{ __('Place order') }}</button>
                </form>
                @include('toko.partials.setuju', ['kalimat' => 'By placing your order you agree to our :a and :b.', 'slug' => ['terms', 'returns']])
                <p class="catatan">{{ __('Your items are held for :hours hours while you pay. Payment options appear on the next page.', ['hours' => $batasJam]) }}</p>
            </aside>
        </div>
    </div>
@endsection
