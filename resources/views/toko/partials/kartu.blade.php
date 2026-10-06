<a class="kartu" href="{{ $k['url'] }}">
    <span class="kartu-foto">
        @if (count($k['foto_semua'] ?? []) > 1)
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
            <span class="tanpa-foto">{{ __('Photo coming soon') }}</span>
        @endif
    </span>
    <span class="kartu-nama">{{ $k['nama'] }}</span>
    <span class="kartu-harga">
        @if ($k['habis'])
            <span class="habis">{{ __('Sold out') }}</span>
        @elseif ($k['mulai_dari'])
            {{ __('from :price', ['price' => $k['harga']]) }}
        @else
            {{ $k['harga'] }}
        @endif
    </span>
    @if ($k['ringkas_opsi'])
        <span class="kartu-meta">{{ implode(', ', $k['ringkas_opsi']) }}</span>
    @endif
</a>
