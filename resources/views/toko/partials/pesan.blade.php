@if (session('status'))
    <div class="wrap"><p class="pesan pesan-ok" role="status">{{ session('status') }}</p></div>
@endif
@if ($errors->hasAny(['keranjang', 'checkout', 'order']))
    <div class="wrap">
        <p class="pesan pesan-galat" role="alert">{{ $errors->first('keranjang') ?: $errors->first('checkout') ?: $errors->first('order') }}</p>
    </div>
@endif
