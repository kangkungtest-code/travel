<?php

namespace App\Support\Oauth;

use Illuminate\Support\Facades\Http;

/**
 * Login sosial lewat OAuth 2.0 / OpenID Connect (authorization code flow).
 * Ditulis langsung tanpa package supaya ringan; tiap provider cukup 3 endpoint.
 * Provider hanya aktif (tombolnya muncul) kalau client id & secret sudah diisi di .env.
 */
abstract class ProviderSosial
{
    abstract public function kode(): string;

    abstract public function nama(): string;

    abstract protected function urlAuthorize(): string;

    abstract protected function scope(): string;

    /** @return array{id: string, email: ?string, email_terverifikasi: bool, nama: ?string} */
    abstract public function profil(string $code): array;

    public static function semua(): array
    {
        return ['google' => new Google, 'line' => new Line];
    }

    /** @return array<string, ProviderSosial> */
    public static function aktif(): array
    {
        return array_filter(self::semua(), fn (self $p) => $p->bisaDipakai());
    }

    public static function cari(string $kode): ?self
    {
        $p = self::semua()[$kode] ?? null;

        return $p && $p->bisaDipakai() ? $p : null;
    }

    /** Kunci sudah diisi DAN fitur login_<kode> dinyalakan Super Admin. */
    public function bisaDipakai(): bool
    {
        return $this->dikonfigurasi() && \App\Support\Fitur::aktif('login_'.$this->kode());
    }

    public function dikonfigurasi(): bool
    {
        return filled($this->config('client_id')) && filled($this->config('client_secret'));
    }

    protected function config(string $kunci): ?string
    {
        return config("services.{$this->kode()}.{$kunci}");
    }

    public function urlCallback(): string
    {
        return route('masuk.sosial.callback', $this->kode());
    }

    public function urlRedirect(string $state): string
    {
        return $this->urlAuthorize().'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config('client_id'),
            'redirect_uri' => $this->urlCallback(),
            'scope' => $this->scope(),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Tukar authorization code dengan token. */
    protected function token(string $url, string $code): array
    {
        return Http::asForm()->acceptJson()->timeout(10)->post($url, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->urlCallback(),
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
        ])->throw()->json();
    }
}
