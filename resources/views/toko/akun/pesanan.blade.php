@extends('layouts.toko', ['judul' => __('Orders')])

@section('isi')
    <div class="wrap halaman">
        <h1 class="judul-halaman">{{ __('Orders') }}</h1>
        @include('toko.akun._nav')

        @forelse ($pesanan as $o)
            <a class="baris-order" href="{{ $o['url'] }}">
                <span><strong>{{ $o['nomor'] }}</strong><br><span class="redup">{{ $o['tanggal'] }}</span></span>
                @include('toko.akun._status', ['status' => $o['status'], 'label' => $o['label_status']])
                <span class="angka">{{ $o['total'] }}</span>
            </a>
        @empty
            <div class="kosong">
                <p>{{ __('No orders yet.') }}</p>
                <a class="tombol" href="{{ route('produk.index') }}">{{ __('Browse the collection') }}</a>
            </div>
        @endforelse

        @if ($orders->hasPages())
            <nav class="paginasi">
                @if ($orders->onFirstPage())<span aria-disabled="true">{{ __('Previous') }}</span>@else<a href="{{ $orders->previousPageUrl() }}">{{ __('Previous') }}</a>@endif
                <span>{{ __('Page :current of :last', ['current' => $orders->currentPage(), 'last' => $orders->lastPage()]) }}</span>
                @if ($orders->hasMorePages())<a href="{{ $orders->nextPageUrl() }}">{{ __('Next') }}</a>@else<span aria-disabled="true">{{ __('Next') }}</span>@endif
            </nav>
        @endif
    </div>
@endsection
