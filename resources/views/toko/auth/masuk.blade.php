@extends('layouts.toko', ['judul' => __('Sign in')])

@section('isi')
    <div class="wrap halaman form-sempit">
        <h1 class="judul-halaman">{{ __('Sign in') }}</h1>

        @include('toko.partials.sosial')

        <form method="post" action="{{ route('login') }}" class="form" novalidate>
            @csrf
            @include('toko.partials.field', ['nama' => 'email', 'label' => __('Email'), 'tipe' => 'email', 'attr' => 'autocomplete="email" required autofocus'])
            @include('toko.partials.field', ['nama' => 'password', 'label' => __('Password'), 'tipe' => 'password', 'attr' => 'autocomplete="current-password" required'])

            <div class="baris-antara">
                <label class="cek"><input type="checkbox" name="ingat" value="1"> {{ __('Keep me signed in') }}</label>
                <a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
            </div>

            <button type="submit" class="tombol tombol-lebar">{{ __('Sign in') }}</button>
        </form>

        <p class="alih">{{ __('New here?') }} <a href="{{ route('daftar') }}">{{ __('Create an account') }}</a></p>
    </div>
@endsection
