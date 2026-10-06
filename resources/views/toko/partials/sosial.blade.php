@if ($sosial)
    <div class="sosial">
        @foreach ($sosial as $kode => $p)
            <a class="tombol tombol-garis" href="{{ route('masuk.sosial', $kode) }}" data-sosial>{{ __('Continue with :provider', ['provider' => $p->nama()]) }}</a>
        @endforeach
    </div>
    <p class="pemisah"><span>{{ __('or use your email') }}</span></p>
@endif
