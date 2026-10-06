<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($s = \App\Support\Seo::untuk(($seo ?? []) + (isset($deskripsi) ? ['deskripsi' => $deskripsi] : []), $judul ?? null))
    <title>{{ $s['judul'] }}</title>
    <meta name="description" content="{{ $s['deskripsi'] }}">
    @if ($s['noindex'])
        <meta name="robots" content="noindex, nofollow">
    @else
        <link rel="canonical" href="{{ $s['kanonik'] }}">
    @endif
    <meta property="og:site_name" content="{{ config('toko.nama') }}">
    <meta property="og:type" content="{{ $s['tipe'] }}">
    <meta property="og:title" content="{{ $s['judul_og'] }}">
    <meta property="og:description" content="{{ $s['deskripsi'] }}">
    <meta property="og:url" content="{{ $s['kanonik'] }}">
    <meta property="og:locale" content="{{ $s['locale'] }}">
    @foreach ($s['locale_lain'] as $l)<meta property="og:locale:alternate" content="{{ $l }}">
    @endforeach
    @if ($s['gambar'])
        <meta property="og:image" content="{{ $s['gambar'] }}">
        <meta property="og:image:width" content="{{ \App\Support\GambarOg::LEBAR }}">
        <meta property="og:image:height" content="{{ \App\Support\GambarOg::TINGGI }}">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    @foreach ($s['jsonld'] as $ld)
        <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
    <link rel="preload" href="{{ asset('fonts/bricolage/bricolage-grotesque-latin-standard-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/toko.css') }}?v={{ filemtime(public_path('css/toko.css')) }}">
    @if ($temaCss = \App\Support\Tema::css())<style>{!! $temaCss !!}</style>
    @endif
    @if ($logo = \App\Support\Tema::logo())<link rel="icon" href="{{ $logo }}">
    @endif
    <script src="{{ asset('js/toko.js') }}?v={{ filemtime(public_path('js/toko.js')) }}" defer></script>
</head>
<body>
    <a class="skip" href="#isi">{{ __('Skip to content') }}</a>

    <header class="topbar">
        <div class="wrap topbar-inner">
            <a class="wordmark" href="{{ route('home') }}">@if ($logo)<img class="logo" src="{{ $logo }}" alt="" width="40" height="40">@endif{{ config('toko.nama') }}</a>

            <nav class="nav" aria-label="{{ __('Main navigation') }}">
                <a href="{{ route('sewa.index') }}" @class(['aktif' => request()->routeIs('sewa.*')])>{{ __('Vehicles') }}</a>
                <a href="{{ route('faq') }}" @class(['aktif' => request()->routeIs('faq')])>{{ __('FAQ') }}</a>
            </nav>

            <div class="akun-nav">
                @if ($pembeli)
                    <a href="{{ route('akun') }}" @class(['aktif' => request()->routeIs('akun*')])>{{ __('Account') }}</a>
                @else
                    <a href="{{ route('login') }}" data-masuk @class(['aktif' => request()->routeIs('login', 'daftar')])>{{ __('Sign in') }}</a>
                @endif
            </div>

            <form class="prefs" method="post" action="{{ route('preferensi') }}" data-auto-submit>
                @csrf
                @if (count(config('toko.locales')) > 1)
                <label>
                    <span class="sr-only">{{ __('Language') }}</span>
                    <select name="locale">
                        @foreach (config('toko.locales') as $kode => $nama)
                            <option value="{{ $kode }}" @selected(app()->getLocale() === $kode)>{{ $nama }}</option>
                        @endforeach
                    </select>
                </label>
                @endif
                @if (count(config('toko.currencies')) > 1)
                <label>
                    <span class="sr-only">{{ __('Currency') }}</span>
                    <select name="currency">
                        @foreach (config('toko.currencies') as $kode)
                            <option value="{{ $kode }}" @selected(\App\Support\TampilanProduk::mataUang() === $kode)>{{ $kode }}</option>
                        @endforeach
                    </select>
                </label>
                @endif
                <noscript><button type="submit">{{ __('Apply') }}</button></noscript>
            </form>
        </div>
    </header>

    <main id="isi">
        @include('toko.partials.pesan')
        @yield('isi')
    </main>

    @if (\App\Support\Fitur::aktif('chatbot'))
    <div class="chat" data-chat data-url="{{ route('chatbot') }}" data-t-galat="{{ __('Sorry, something went wrong. Please try again.') }}">
        <button type="button" class="chat-buka" aria-expanded="false" aria-controls="chat-panel" data-chat-buka>{{ __('Ask us') }}</button>
        <section class="chat-panel" id="chat-panel" role="dialog" aria-modal="false" aria-label="{{ __('Ask us') }}" hidden>
            <header class="chat-kepala">
                <p class="chat-judul">{{ __('Ask us') }}</p>
                <button type="button" class="tautan" data-chat-tutup aria-label="{{ __('Close') }}">{{ __('Close') }}</button>
            </header>
            <div class="chat-isi" data-chat-isi aria-live="polite"></div>
            <div class="chat-saran" data-chat-saran></div>
            <form class="chat-form" data-chat-form>
                <label class="sr-only" for="chat-pesan">{{ __('Type your question') }}</label>
                <input id="chat-pesan" name="pesan" type="text" maxlength="500" autocomplete="off" placeholder="{{ __('Type your question') }}" required>
                <button type="submit" class="tombol tombol-kecil">{{ __('Send') }}</button>
            </form>
            <p class="chat-catatan">{{ __('Automated answers. For anything else, chat with our team.') }}</p>
        </section>
    </div>
    @endif

    @if (! $pembeli && ! request()->routeIs('login', 'daftar', 'password.*'))
        @include('toko.partials.dialog-masuk')
    @endif

    <footer class="footer">
        <div class="wrap footer-inner">
            <p class="wordmark wordmark-kecil">{{ config('toko.nama') }}</p>
            <p>{{ __('Prices shown in :currency. Converted from rupiah at the store\'s daily rate.', ['currency' => \App\Support\TampilanProduk::mataUang()]) }}</p>
            <p class="footer-tautan">
                <a href="{{ route('faq') }}">{{ __('FAQ') }}</a>
                @foreach (\App\Models\HalamanKebijakan::tautan() as $kb)
                    <a href="{{ $kb['url'] }}">{{ $kb['judul'] }}</a>
                @endforeach
            </p>
            @php($kontak = \App\Support\KontakAdmin::tautan())
            @php($medsos = \App\Support\KontakAdmin::medsos())
            @if ($kontak || $medsos)
                <div class="footer-kontak">
                    @if ($kontak)
                        <p class="footer-tautan" aria-label="{{ __('Contact us') }}">
                            @foreach ($kontak as $k)
                                <a href="{{ $k['url'] }}" @if (in_array($k['jenis'], ['wa', 'line'], true)) target="_blank" rel="noopener" @endif>{{ match ($k['jenis']) {
                                    'wa' => 'WhatsApp',
                                    'line' => 'LINE',
                                    'email' => \App\Models\Pengaturan::ambil('kontak.email'),
                                    default => \App\Support\KontakAdmin::teksTelepon(),
                                } }}</a>
                            @endforeach
                        </p>
                    @endif
                    @if ($medsos)
                        <p class="footer-tautan footer-medsos" aria-label="{{ __('Follow us') }}">
                            <span>{{ __('Follow us') }}:</span>
                            @foreach ($medsos as $m)
                                <a href="{{ $m['url'] }}" target="_blank" rel="noopener me">{{ $m['label'] }}</a>
                            @endforeach
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </footer>
</body>
</html>
