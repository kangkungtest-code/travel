{{-- Pop-up masuk/daftar untuk tamu. Tanpa JavaScript, tautan tetap membuka /masuk & /daftar. --}}
@php($dialogErr = $errors->getBag('dialog'))
@php($tabAwal = old('_dialog') === 'daftar' ? 'daftar' : 'masuk')
<dialog class="dialog-masuk" data-dialog-masuk aria-labelledby="dialog-masuk-judul"
        @if ($dialogErr->any()) data-buka-awal="{{ $tabAwal }}" @endif>
    <div class="dialog-isi">
        <div class="dialog-kepala">
            <h2 id="dialog-masuk-judul" class="sr-only">{{ __('Sign in') }}</h2>
            <div class="dialog-tab" role="tablist">
                <button type="button" role="tab" id="tab-masuk" aria-controls="panel-masuk" data-tab="masuk" aria-selected="{{ $tabAwal === 'masuk' ? 'true' : 'false' }}">{{ __('Sign in') }}</button>
                <button type="button" role="tab" id="tab-daftar" aria-controls="panel-daftar" data-tab="daftar" aria-selected="{{ $tabAwal === 'daftar' ? 'true' : 'false' }}">{{ __('Create account') }}</button>
            </div>
            <button type="button" class="dialog-tutup" data-tutup aria-label="{{ __('Close') }}">&times;</button>
        </div>

        @include('toko.partials.sosial', ['sosial' => \App\Support\Oauth\ProviderSosial::aktif()])

        <div role="tabpanel" id="panel-masuk" aria-labelledby="tab-masuk" data-panel="masuk" @if ($tabAwal !== 'masuk') hidden @endif>
            <form method="post" action="{{ route('login') }}" class="form" novalidate>
                @csrf
                <input type="hidden" name="_dialog" value="masuk">
                <input type="hidden" name="kembali" value="" data-kembali>
                @include('toko.partials.field', ['nama' => 'email', 'awalan' => 'dm', 'bag' => 'dialog', 'pakaiOld' => $tabAwal === 'masuk', 'label' => __('Email'), 'tipe' => 'email', 'attr' => 'autocomplete="email" required'])
                @include('toko.partials.field', ['nama' => 'password', 'awalan' => 'dm', 'bag' => 'dialog', 'label' => __('Password'), 'tipe' => 'password', 'attr' => 'autocomplete="current-password" required'])
                <div class="baris-antara">
                    <label class="cek"><input type="checkbox" name="ingat" value="1"> {{ __('Keep me signed in') }}</label>
                    <a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
                </div>
                <button type="submit" class="tombol tombol-lebar">{{ __('Sign in') }}</button>
            </form>
        </div>

        <div role="tabpanel" id="panel-daftar" aria-labelledby="tab-daftar" data-panel="daftar" @if ($tabAwal !== 'daftar') hidden @endif>
            <form method="post" action="{{ route('daftar') }}" class="form" novalidate>
                @csrf
                <input type="hidden" name="_dialog" value="daftar">
                <input type="hidden" name="kembali" value="" data-kembali>
                @include('toko.partials.field', ['nama' => 'nama_lengkap', 'awalan' => 'dd', 'bag' => 'dialog', 'pakaiOld' => $tabAwal === 'daftar', 'label' => __('Full name'), 'attr' => 'autocomplete="name" required'])
                @include('toko.partials.field', ['nama' => 'email', 'awalan' => 'dd', 'bag' => 'dialog', 'pakaiOld' => $tabAwal === 'daftar', 'label' => __('Email'), 'tipe' => 'email', 'attr' => 'autocomplete="email" required'])
                @include('toko.partials.field', ['nama' => 'password', 'awalan' => 'dd', 'bag' => 'dialog', 'label' => __('Password'), 'tipe' => 'password', 'attr' => 'autocomplete="new-password" required', 'bantuan' => __('At least 8 characters.')])
                @include('toko.partials.field', ['nama' => 'password_confirmation', 'awalan' => 'dd', 'bag' => 'dialog', 'label' => __('Repeat password'), 'tipe' => 'password', 'attr' => 'autocomplete="new-password" required'])
                <button type="submit" class="tombol tombol-lebar">{{ __('Create account') }}</button>
                @include('toko.partials.setuju', ['kalimat' => 'By creating an account you agree to our :a and :b.', 'slug' => ['terms', 'privacy']])
            </form>
        </div>
    </div>
</dialog>
