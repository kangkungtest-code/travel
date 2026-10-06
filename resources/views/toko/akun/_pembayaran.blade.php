{{-- Bagian pembayaran untuk order menunggu_pembayaran. $b = TampilanPembayaran::untuk() --}}
<section class="blok blok-sorot bayar" @if ($b['aktif']) data-cek-status="{{ route('akun.pesanan.status', $o['nomor']) }}" data-status-awal="{{ $o['status'] }}" @endif>
    <h2>{{ __('Pay :total by :deadline', ['total' => $b['aktif']['jumlah'] ?? $o['total'], 'deadline' => $o['batas_bayar']]) }}</h2>
    <p>{{ __('Your items are held until then. If the order isn\'t paid in time it is cancelled automatically.') }}</p>

    @if (! $b['ada_gateway'])
        <button type="button" class="tombol" disabled>{{ __('Online payment opens soon') }}</button>
    @elseif ($b['aktif'])
        @php($a = $b['aktif'])
        <div class="tagihan">
            @if ($a['qr_svg'])
                <p><strong>{{ __('Scan with any QRIS-enabled banking or e-wallet app') }}</strong></p>
                <div class="qr" role="img" aria-label="QRIS">{!! $a['qr_svg'] !!}</div>
            @else
                <p><strong>{{ __(':bank virtual account number', ['bank' => $a['bank']]) }}</strong></p>
                <p class="nomor-va">
                    <span data-salin-isi>{{ $a['nomor_va'] }}</span>
                    <button type="button" class="tautan" data-salin data-t-tersalin="{{ __('Copied') }}">{{ __('Copy') }}</button>
                </p>
                <p class="redup">{{ __('Transfer the exact amount from your :bank app, ATM or internet banking.', ['bank' => $a['bank']]) }}</p>
            @endif
            <p class="angka-besar">{{ $a['jumlah'] }}</p>
            <p class="redup" data-teks-status>{{ __('This page updates automatically once the payment arrives.') }}</p>
        </div>
        @if ($b['simulasi'])
            {{-- Hanya muncul di dev/demo dengan kunci test Xendit; tidak diterjemahkan. --}}
            <div class="blok-uji">
                <p><strong>Mode test Xendit</strong> — QR / VA ini tidak bisa dibayar dengan aplikasi sungguhan.</p>
                <form method="post" action="{{ route('akun.pesanan.simulasi', $o['nomor']) }}">
                    @csrf
                    <button type="submit" class="tombol tombol-garis tombol-kecil">Simulasikan bayar</button>
                </form>
                <p class="redup">
                    Webhook Xendit terakhir:
                    @if ($b['simulasi']['webhook'])
                        {{ $b['simulasi']['webhook']['waktu']->timezone(config('toko.zona_waktu'))->format('d M H:i:s') }} — {{ $b['simulasi']['webhook']['hasil'] }}
                    @else
                        belum pernah ada yang masuk
                    @endif
                </p>
            </div>
        @endif
        <details class="ganti-metode">
            <summary>{{ __('Use a different payment method') }}</summary>
            @include('toko.akun._pilih-metode', ['o' => $o, 'b' => $b])
        </details>
    @elseif ($b['pilihan'])
        @include('toko.akun._pilih-metode', ['o' => $o, 'b' => $b])
    @else
        <p class="habis">{{ __('No payment method is available for this order right now. Please contact us.') }}</p>
    @endif
</section>
