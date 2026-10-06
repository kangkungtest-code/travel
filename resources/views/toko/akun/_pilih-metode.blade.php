<form method="post" action="{{ route('akun.pesanan.bayar', $o['nomor']) }}" class="pilih-metode" data-pilih-metode>
    @csrf
    <fieldset>
        <legend class="sr-only">{{ __('Payment method') }}</legend>
        @foreach ($b['pilihan'] as $i => $m)
            <label class="kartu-alamat metode">
                <input type="radio" name="metode" value="{{ $m['kode'] }}" @checked($i === 0) required>
                <span>
                    <strong>{{ $m['label'] }}</strong>
                    <span class="angka">{{ $m['tampil'] }}</span>
                    @if ($m['beda_mata_uang'])
                        <br><span class="redup">{{ __('Charged in :currency at today\'s rate.', ['currency' => $m['mata_uang']]) }}</span>
                    @endif
                    @if ($m['kode'] === 'xendit_va')
                        <span class="pilih-bank">
                            <select name="bank" aria-label="{{ __('Bank') }}">
                                @foreach ($b['bank'] as $bank)
                                    <option value="{{ $bank }}">{{ $bank }}</option>
                                @endforeach
                            </select>
                        </span>
                    @endif
                </span>
            </label>
        @endforeach
    </fieldset>
    <button type="submit" class="tombol">{{ __('Pay now') }}</button>
</form>
