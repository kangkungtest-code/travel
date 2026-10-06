@extends('layouts.toko', ['judul' => __('Cart')])

@section('isi')
    <div class="wrap halaman">
        <h1 class="judul-halaman">{{ __('Cart') }}</h1>

        @if ($items->isEmpty())
            <div class="kosong">
                <p>{{ __('Your cart is empty.') }}</p>
                <a class="tombol" href="{{ route('produk.index') }}">{{ __('Browse the collection') }}</a>
            </div>
        @else
            <div class="dua-kolom">
                <ul class="daftar-barang">
                    @foreach ($items as $b)
                        @include('toko.partials.baris-barang', ['b' => $b, 'ubah' => true])
                    @endforeach
                </ul>

                <aside class="ringkasan">
                    <dl>
                        <div><dt>{{ __('Subtotal') }}</dt><dd>{{ $subtotal }}</dd></div>
                    </dl>
                    <p class="catatan">{{ __('Shipping is calculated at checkout from your address and the total weight.') }}</p>
                    @if ($ada_masalah)
                        <p class="habis">{{ __('Some items need your attention before checkout.') }}</p>
                        <button type="button" class="tombol tombol-lebar" disabled>{{ __('Checkout') }}</button>
                    @else
                        <a class="tombol tombol-lebar" href="{{ route('checkout') }}" @guest('web') data-masuk data-kembali="{{ route('checkout', absolute: false) }}" @endguest>{{ __('Checkout') }}</a>
                    @endif
                    @guest('web')
                        <p class="catatan">{{ __('You will be asked to sign in or create an account first.') }}</p>
                    @endguest
                </aside>
            </div>
        @endif
    </div>
@endsection
