<?php

namespace App\Support\Oauth;

use Illuminate\Support\Facades\Http;

class Google extends ProviderSosial
{
    public function kode(): string
    {
        return 'google';
    }

    public function nama(): string
    {
        return 'Google';
    }

    protected function urlAuthorize(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function scope(): string
    {
        return 'openid email profile';
    }

    public function profil(string $code): array
    {
        $token = $this->token('https://oauth2.googleapis.com/token', $code);

        $u = Http::withToken($token['access_token'])->acceptJson()->timeout(10)
            ->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();

        return [
            'id' => (string) $u['sub'],
            'email' => $u['email'] ?? null,
            'email_terverifikasi' => (bool) ($u['email_verified'] ?? false),
            'nama' => $u['name'] ?? null,
        ];
    }
}
