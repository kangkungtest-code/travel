{{-- Satu field form: @include('toko.partials.field', ['nama' => 'email', 'label' => __('Email'), 'tipe' => 'email', 'nilai' => ..., 'attr' => 'autocomplete=email']) --}}
@php($bag = $bag ?? 'default')
@php($fid = ($awalan ?? 'f').'-'.$nama)
@php($pesan = $errors->getBag($bag)->first($nama))
<div @class(['field', 'field-galat' => $pesan])>
    <label for="{{ $fid }}">{{ $label }}</label>
    @if (($tipe ?? 'text') === 'textarea')
        <textarea id="{{ $fid }}" name="{{ $nama }}" rows="3" {!! $attr ?? '' !!} @if ($pesan) aria-invalid="true" aria-describedby="e-{{ $fid }}" @endif>{{ old($nama, $nilai ?? '') }}</textarea>
    @else
        <input id="{{ $fid }}" type="{{ $tipe ?? 'text' }}" name="{{ $nama }}" value="{{ ($tipe ?? 'text') === 'password' ? '' : (($pakaiOld ?? true) ? old($nama, $nilai ?? '') : ($nilai ?? '')) }}" {!! $attr ?? '' !!} @if ($pesan) aria-invalid="true" aria-describedby="e-{{ $fid }}" @endif>
    @endif
    @if ($pesan)<p class="field-pesan" id="e-{{ $fid }}">{{ $pesan }}</p>@endif
    @isset($bantuan)<p class="field-bantuan">{{ $bantuan }}</p>@endisset
</div>
