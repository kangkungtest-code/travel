@extends('layouts.toko', ['judul' => __('Choose a new password')])

@section('isi')
    <div class="wrap halaman form-sempit">
        <h1 class="judul-halaman">{{ __('Choose a new password') }}</h1>

        <form method="post" action="{{ route('password.update') }}" class="form" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            @include('toko.partials.field', ['nama' => 'email', 'label' => __('Email'), 'tipe' => 'email', 'nilai' => $email, 'attr' => 'autocomplete="email" required'])
            @include('toko.partials.field', ['nama' => 'password', 'label' => __('New password'), 'tipe' => 'password', 'attr' => 'autocomplete="new-password" required autofocus', 'bantuan' => __('At least 8 characters.')])
            @include('toko.partials.field', ['nama' => 'password_confirmation', 'label' => __('Repeat password'), 'tipe' => 'password', 'attr' => 'autocomplete="new-password" required'])
            <button type="submit" class="tombol tombol-lebar">{{ __('Save new password') }}</button>
        </form>
    </div>
@endsection
