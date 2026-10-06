<?php

namespace App\Support\Oauth;

use Illuminate\Support\Facades\Http;

/**
 * LINE Login v2.1. Email hanya dikirim kalau channel sudah mendapat izin email
 * dari LINE dan pengguna menyetujuinya — kalau tidak, pembeli diminta mengisi email.
 */
class Line extends ProviderSosial
{
    public function kode(): string
    {
        return 'line';
    }

    public function nama(): string
    {
        return 'LINE';
    }

    protected function urlAuthorize(): string
    {
        return 'https://access.line.me/oauth2/v2.1/authorize';
    }

    protected function scope(): string
    {
        return 'profile openid email';
    }

    public function profil(string $code): array
    {
        $token = $this->token('https://api.line.me/oauth2/v2.1/token', $code);

        // Verifikasi id_token di server LINE sekaligus membaca isinya.
        $u = Http::asForm()->acceptJson()->timeout(10)->post('https://api.line.me/oauth2/v2.1/verify', [
            'id_token' => $token['id_token'],
            'client_id' => $this->config('client_id'),
        ])->throw()->json();

        return [
            'id' => (string) $u['sub'],
            'email' => $u['email'] ?? null,
            'email_terverifikasi' => filled($u['email'] ?? null),
            'nama' => $u['name'] ?? null,
        ];
    }
}
