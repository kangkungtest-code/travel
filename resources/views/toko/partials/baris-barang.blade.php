{{-- Satu baris barang (keranjang/checkout). $b dari Keranjang::ringkasan(); $ubah = tampilkan kontrol qty --}}
<li class="barang">
    <a class="barang-foto" href="{{ $b['url'] }}" tabindex="-1">
        @if ($b['foto'])<img src="{{ $b['foto'] }}" alt="" width="400" height="400" loading="lazy">@endif
    </a>
    <div class="barang-info">
        <a class="barang-nama" href="{{ $b['url'] }}">{{ $b['nama'] }}</a>
        @if ($b['opsi'])<span class="barang-opsi">{{ $b['opsi'] }}</span>@endif
        <span class="barang-harga">{{ $b['harga_tampil'] }}</span>
        @if ($b['masalah'])<span class="habis">{{ $b['masalah'] }}</span>@endif
    </div>
    <div class="barang-aksi">
        @if ($ubah ?? false)
            <form method="post" action="{{ route('keranjang.ubah', $b['item']) }}" class="qty-form">
                @csrf @method('patch')
                <label class="sr-only" for="qty-{{ $b['item']->id }}">{{ __('Quantity') }}</label>
                <input id="qty-{{ $b['item']->id }}" type="number" name="qty" value="{{ $b['item']->qty }}" min="0" max="{{ config('toko.order.maks_qty_per_item') }}" inputmode="numeric" data-auto-submit-field>
                <noscript><button type="submit" class="tautan">{{ __('Update') }}</button></noscript>
            </form>
            <form method="post" action="{{ route('keranjang.hapus', $b['item']) }}">
                @csrf @method('delete')
                <button type="submit" class="tautan">{{ __('Remove') }}</button>
            </form>
        @else
            <span class="barang-qty">× {{ $b['item']->qty }}</span>
        @endif
        <span class="barang-total">{{ $b['total_tampil'] }}</span>
    </div>
</li>
