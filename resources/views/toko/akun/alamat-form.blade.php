@extends('layouts.toko', ['judul' => $alamat->exists ? __('Edit address') : __('Add address')])

@section('isi')
    <div class="wrap halaman form-sempit">
        <h1 class="judul-halaman">{{ $alamat->exists ? __('Edit address') : __('Add address') }}</h1>

        <form method="post" action="{{ $alamat->exists ? route('akun.alamat.update', $alamat) : route('akun.alamat.store') }}" class="form" novalidate>
            @csrf
            @if ($alamat->exists) @method('put') @endif
            <input type="hidden" name="kembali" value="{{ $kembali === route('checkout') ? 'checkout' : '' }}">

            @include('toko.partials.field', ['nama' => 'label', 'label' => __('Label'), 'nilai' => $alamat->label, 'attr' => 'required placeholder="'.e(__('Home, Office')).'"'])
            <div class="dua-field">
                @include('toko.partials.field', ['nama' => 'nama_penerima', 'label' => __('Recipient name'), 'nilai' => $alamat->nama_penerima, 'attr' => 'autocomplete="name" required'])
                @include('toko.partials.field', ['nama' => 'telepon', 'label' => __('Phone'), 'tipe' => 'tel', 'nilai' => $alamat->telepon, 'attr' => 'autocomplete="tel" required'])
            </div>
            <div @class(['field', 'field-galat' => $errors->has('negara')])>
                <label for="f-negara">{{ __('Country') }}</label>
                <select id="f-negara" name="negara" autocomplete="country">
                    @foreach ($negara as $kode => $nama)
                        <option value="{{ $kode }}" @selected(old('negara', $alamat->negara) === $kode)>{{ $nama }}</option>
                    @endforeach
                </select>
                @error('negara')<p class="field-pesan">{{ $message }}</p>@enderror
                <p class="field-bantuan">{{ __('We currently ship to the countries listed.') }}</p>
            </div>
            <div class="dua-field">
                @include('toko.partials.field', ['nama' => 'kota', 'label' => __('City'), 'nilai' => $alamat->kota, 'attr' => 'autocomplete="address-level2" required'])
                @include('toko.partials.field', ['nama' => 'kode_pos', 'label' => __('Postal code'), 'nilai' => $alamat->kode_pos, 'attr' => 'autocomplete="postal-code" required'])
            </div>
            @include('toko.partials.field', ['nama' => 'detail_alamat', 'label' => __('Street address'), 'tipe' => 'textarea', 'nilai' => $alamat->detail_alamat, 'attr' => 'autocomplete="street-address" required', 'bantuan' => __('Street, number, district and any delivery notes.')])

            <label class="cek"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $alamat->is_default))> {{ __('Use as default address') }}</label>

            <div class="baris-antara">
                <a href="{{ $kembali }}">{{ __('Cancel') }}</a>
                <button type="submit" class="tombol">{{ __('Save address') }}</button>
            </div>
        </form>
    </div>
@endsection
