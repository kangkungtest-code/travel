<nav class="sub-nav" aria-label="{{ __('Account') }}">
    <a href="{{ route('akun') }}" @if (request()->routeIs('akun')) aria-current="page" @endif>{{ __('Overview') }}</a>
    <a href="{{ route('akun.pesanan') }}" @if (request()->routeIs('akun.pesanan*')) aria-current="page" @endif>{{ __('Orders') }}</a>
    <form method="post" action="{{ route('keluar') }}">
        @csrf
        <button type="submit" class="tautan">{{ __('Sign out') }}</button>
    </form>
</nav>
