{{-- Catatan "dengan ... kamu setuju dengan ..." berisi tautan kebijakan. $kalimat memakai :a dan :b, $slug = [slug a, slug b]. --}}
@php($kb = \App\Models\HalamanKebijakan::tautan())
@if (isset($kb[$slug[0]], $kb[$slug[1]]))
    @php($tautan = fn ($k) => '<a href="'.e($kb[$k]['url']).'" target="_blank">'.e($kb[$k]['judul']).'</a>')
    <p class="catatan-setuju">{!! __($kalimat, ['a' => $tautan($slug[0]), 'b' => $tautan($slug[1])]) !!}</p>
@endif
