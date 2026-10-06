<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Klien Firebase Cloud Messaging HTTP v1 tanpa SDK: service account -> JWT (RS256)
 * -> access token OAuth (di-cache ~50 menit) -> messages:send.
 */
class Fcm
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Hasil kirim per token. */
    public const TERKIRIM = 'terkirim';
    public const TOKEN_MATI = 'token_mati';
    public const GAGAL = 'gagal';

    public function aktif(): bool
    {
        return $this->kredensial() !== null;
    }

    /**
     * @param  array<string, string>  $data  semua nilai harus string (aturan FCM)
     * @return string salah satu konstanta hasil
     */
    public function kirim(string $token, string $judul, string $isi, array $data = []): string
    {
        $kred = $this->kredensial() ?? throw new RuntimeException('FIREBASE_CREDENTIALS belum diisi.');

        $res = Http::withToken($this->accessToken($kred))
            ->acceptJson()->asJson()->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$kred['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $judul, 'body' => $isi],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'pesanan']],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

        if ($res->successful()) {
            return self::TERKIRIM;
        }

        $kode = $res->json('error.details.0.errorCode') ?? $res->json('error.status');
        if ($res->status() === 404 || in_array($kode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            return self::TOKEN_MATI;
        }

        Log::warning('FCM gagal mengirim', ['status' => $res->status(), 'kode' => $kode]);

        return self::GAGAL;
    }

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    private function kredensial(): ?array
    {
        $isi = trim((string) config('services.fcm.credentials'));
        if ($isi === '') {
            return null;
        }

        // Boleh JSON mentah atau base64 (lebih aman disimpan sebagai secret satu baris).
        $json = str_starts_with($isi, '{') ? $isi : base64_decode(preg_replace('/\s+/', '', $isi), true);
        $kred = $json ? json_decode($json, true) : null;

        return isset($kred['project_id'], $kred['client_email'], $kred['private_key']) ? $kred : null;
    }

    private function accessToken(array $kred): string
    {
        return Cache::remember('fcm.token.'.md5($kred['client_email']), now()->addMinutes(50), function () use ($kred) {
            $sekarang = time();
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $kepala = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $klaim = $b64(json_encode([
                'iss' => $kred['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $sekarang,
                'exp' => $sekarang + 3600,
            ]));

            if (! openssl_sign("{$kepala}.{$klaim}", $tanda, $kred['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Private key service account Firebase tidak valid.');
            }

            return Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$kepala}.{$klaim}.".$b64($tanda),
            ])->throw()->json('access_token');
        });
    }
}
