@extends('layouts.toko', ['judul' => __('Reset your password')])

@section('isi')
    <div class="wrap halaman form-sempit">
        <h1 class="judul-halaman">{{ __('Reset your password') }}</h1>
        <p class="lead-kecil">{{ __('Enter the email you signed up with. We will send you a link to choose a new password.') }}</p>

        <form method="post" action="{{ route('password.email') }}" class="form" novalidate>
            @csrf
            @include('toko.partials.field', ['nama' => 'email', 'label' => __('Email'), 'tipe' => 'email', 'attr' => 'autocomplete="email" required autofocus'])
            <button type="submit" class="tombol tombol-lebar">{{ __('Send reset link') }}</button>
        </form>

        <p class="alih"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
    </div>
@endsection
