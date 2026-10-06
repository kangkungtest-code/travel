@extends('layouts.toko', ['judul' => __('Verify your email')])

@section('isi')
    <div class="wrap halaman form-sempit">
        <h1 class="judul-halaman">{{ __('Verify your email') }}</h1>
        <p>{{ __('We sent a verification link to :email. Open it to continue to checkout.', ['email' => auth('web')->user()->email]) }}</p>
        <p class="redup">{{ __('Can\'t find it? Check your spam folder, or send a new link.') }}</p>
        <form method="post" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="tombol">{{ __('Resend verification email') }}</button>
        </form>
        <p class="alih"><a href="{{ route('keranjang') }}">{{ __('Back to cart') }}</a></p>
    </div>
@endsection
