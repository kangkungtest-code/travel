{{-- Form cari sewa. $cari = PencarianSewa, $aksi = URL tujuan (default daftar kendaraan). --}}
@php($isi = $cari->isian())
@php($lokasiList = \App\Support\TampilanKendaraan::lokasi())
<form class="form-cari" method="get" action="{{ $aksi ?? route('sewa.index') }}">
    <div @class(['field', 'field-galat' => isset($cari->galat['lokasi'])])>
        <label for="c-lokasi">{{ __('Pick-up location') }}</label>
        <select id="c-lokasi" name="lokasi" required>
            @foreach ($lokasiList as $l)
                <option value="{{ $l['id'] }}" @selected($isi['lokasi'] === $l['id'])>{{ $l['nama'] }}</option>
            @endforeach
        </select>
        @isset($cari->galat['lokasi'])<p class="field-pesan">{{ $cari->galat['lokasi'] }}</p>@endisset
    </div>
    <div @class(['field', 'field-galat' => isset($cari->galat['mulai'])])>
        <label for="c-mulai">{{ __('Pick-up') }}</label>
        <input id="c-mulai" type="datetime-local" name="mulai" value="{{ $isi['mulai'] }}" min="{{ $isi['min'] }}" step="1800" required>
        @isset($cari->galat['mulai'])<p class="field-pesan">{{ $cari->galat['mulai'] }}</p>@endisset
    </div>
    <div @class(['field', 'field-galat' => isset($cari->galat['selesai'])])>
        <label for="c-selesai">{{ __('Return') }}</label>
        <input id="c-selesai" type="datetime-local" name="selesai" value="{{ $isi['selesai'] }}" min="{{ $isi['min'] }}" step="1800" required>
        @isset($cari->galat['selesai'])<p class="field-pesan">{{ $cari->galat['selesai'] }}</p>@endisset
    </div>
    <fieldset class="field cari-mode">
        <legend>{{ __('Rental type') }}</legend>
        <div class="opsi-pilihan">
            @foreach (\App\Support\TampilanKendaraan::pilihanMode() as $kode => $label)
                <label class="chip">
                    <input type="radio" name="mode" value="{{ $kode }}" @checked($isi['mode'] === $kode)>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
    @isset($jenis)<input type="hidden" name="jenis" value="{{ $jenis }}">@endisset
    <button type="submit" class="tombol">{{ $tombol ?? __('Find a vehicle') }}</button>
    <p class="field-bantuan cari-zona">{{ __('Times are local time (:zone).', ['zone' => 'WITA, UTC+8']) }}</p>
</form>
