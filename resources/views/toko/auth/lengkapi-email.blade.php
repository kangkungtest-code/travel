@extends('layouts.toko', ['judul' => __('One more step')])

@section('isi')
    <div class="wrap halaman form-sempit">
        <h1 class="judul-halaman">{{ __('One more step') }}</h1>
        <p class="lead-kecil">{{ __(':provider did not share your email. Add it so we can send order updates.', ['provider' => $provider]) }}</p>

        <form method="post" action="{{ route('masuk.sosial.email') }}" class="form" novalidate>
            @csrf
            @include('toko.partials.field', ['nama' => 'nama_lengkap', 'label' => __('Full name'), 'nilai' => $nama, 'attr' => 'autocomplete="name" required'])
            @include('toko.partials.field', ['nama' => 'email', 'label' => __('Email'), 'tipe' => 'email', 'attr' => 'autocomplete="email" required autofocus'])
            <button type="submit" class="tombol tombol-lebar">{{ __('Create account') }}</button>
        </form>
    </div>
@endsection
