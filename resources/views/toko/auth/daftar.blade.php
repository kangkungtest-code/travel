@extends('layouts.toko', ['judul' => __('Create an account')])

@section('isi')
    <div class="wrap halaman form-sempit">
        <h1 class="judul-halaman">{{ __('Create an account') }}</h1>
        <p class="lead-kecil">{{ __('You need an account to place orders and track them.') }}</p>

        @include('toko.partials.sosial')

        <form method="post" action="{{ route('daftar') }}" class="form" novalidate>
            @csrf
            @include('toko.partials.field', ['nama' => 'nama_lengkap', 'label' => __('Full name'), 'attr' => 'autocomplete="name" required autofocus'])
            @include('toko.partials.field', ['nama' => 'email', 'label' => __('Email'), 'tipe' => 'email', 'attr' => 'autocomplete="email" required'])
            @include('toko.partials.field', ['nama' => 'password', 'label' => __('Password'), 'tipe' => 'password', 'attr' => 'autocomplete="new-password" required', 'bantuan' => __('At least 8 characters.')])
            @include('toko.partials.field', ['nama' => 'password_confirmation', 'label' => __('Repeat password'), 'tipe' => 'password', 'attr' => 'autocomplete="new-password" required'])

            <button type="submit" class="tombol tombol-lebar">{{ __('Create account') }}</button>
            @include('toko.partials.setuju', ['kalimat' => 'By creating an account you agree to our :a and :b.', 'slug' => ['terms', 'privacy']])
        </form>

        <p class="alih">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign in') }}</a></p>
    </div>
@endsection
