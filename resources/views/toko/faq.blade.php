@extends('layouts.toko', ['judul' => __('Frequently asked questions')])

@section('isi')
    <div class="wrap halaman sempit">
        <h1 class="judul-halaman">{{ __('Frequently asked questions') }}</h1>

        @forelse ($faq as $f)
            <details class="faq" @if ($loop->first) open @endif>
                <summary>{{ $f->getTranslation('pertanyaan_terjemahan', app()->getLocale()) }}</summary>
                <p>{{ $f->getTranslation('jawaban_terjemahan', app()->getLocale()) }}</p>
            </details>
        @empty
            <p class="kosong">{{ __('No questions yet.') }}</p>
        @endforelse

        @if ($kontak = \App\Support\KontakAdmin::tautan(__('Hi, I have a question about :store.', ['store' => config('toko.nama')])))
            <section class="blok blok-sorot faq-kontak">
                <h2>{{ __('Still have a question?') }}</h2>
                <p>{{ __('Chat with our team directly.') }}</p>
                <div class="chat-kontak">
                    @foreach ($kontak as $k)
                        <a class="tombol tombol-kecil chat-{{ $k['jenis'] }}" href="{{ $k['url'] }}" target="_blank" rel="noopener">{{ $k['label'] }}</a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
