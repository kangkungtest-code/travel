<a @class(['kartu', 'kartu-penuh' => $k['tersedia'] === false]) href="{{ $k['url'] }}">
    <span class="kartu-foto">
        @if (count($k['foto_semua']) > 1)
            <span class="geser" data-geser data-otomatis="3500">
                <span class="geser-jalur" data-geser-jalur>
                    @foreach ($k['foto_semua'] as $f)
                        <img class="geser-slide" src="{{ $f }}" alt="" loading="lazy" width="400" height="400" draggable="false">
                    @endforeach
                </span>
                <span class="geser-titik" data-geser-titik aria-hidden="true"></span>
            </span>
        @elseif ($k['foto'])
            <img src="{{ $k['foto'] }}" alt="" loading="lazy" width="400" height="400">
        @else
            <span class="tanpa-foto kendaraan-siluet" aria-hidden="true">
                <svg viewBox="0 0 64 32" width="96" height="48"><path d="M6 22h52v-6l-8-2-8-7H22l-8 7-8 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="17" cy="23" r="4" fill="var(--paper)" stroke="currentColor" stroke-width="2"/><circle cx="47" cy="23" r="4" fill="var(--paper)" stroke="currentColor" stroke-width="2"/></svg>
            </span>
        @endif
    </span>
    <span class="kartu-nama">{{ $k['nama'] }}</span>
    <span class="kartu-meta">{{ $k['ringkasan'] }}</span>
    <span class="kartu-harga">
        @if ($k['tersedia'] === false)
            <span class="habis">{{ __('Not available for these dates') }}</span>
        @elseif ($k['total'])
            <strong>{{ $k['total'] }}</strong> <span class="kartu-meta">· {{ $k['durasi'] }}</span>
        @elseif ($k['mulai_dari'])
            {{ __('from :price / day', ['price' => $k['mulai_dari']]) }}
        @endif
    </span>
</a>
