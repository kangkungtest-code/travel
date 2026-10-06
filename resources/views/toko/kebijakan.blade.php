@php($judul = $h->getTranslation('judul_terjemahan', app()->getLocale()))
@extends('layouts.toko', ['judul' => $judul])

@section('isi')
    <div class="wrap halaman sempit">
        <h1 class="judul-halaman">{{ $judul }}</h1>
        <p class="redup">{{ __('Last updated :date', ['date' => $h->updated_at->timezone(config('toko.zona_waktu'))->locale(str_replace('_', '-', app()->getLocale()))->isoFormat('D MMMM YYYY')]) }}</p>

        <div class="teks-kebijakan">{!! $h->isiHtml() !!}</div>

        @if (count($lain) > 1)
            <nav class="kebijakan-lain" aria-label="{{ __('Store policies') }}">
                @foreach ($lain as $slug => $l)
                    <a href="{{ $l['url'] }}" @if ($slug === $h->slug) aria-current="page" @endif>{{ $l['judul'] }}</a>
                @endforeach
            </nav>
        @endif
    </div>
@endsection
