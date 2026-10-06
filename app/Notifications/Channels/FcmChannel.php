<?php

namespace App\Notifications\Channels;

use App\Models\PerangkatAdmin;
use App\Support\Fcm;
use Illuminate\Notifications\Notification;

/** Channel notifikasi push ke semua HP admin yang terdaftar. Diam saja kalau Firebase belum diisi. */
class FcmChannel
{
    public function __construct(private Fcm $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $this->fcm->aktif() || ! \App\Support\Fitur::aktif('aplikasi_mobile') || ! method_exists($notification, 'toFcm')) {
            return;
        }

        $tokens = $notifiable->routeNotificationFor('fcm', $notification) ?: [];
        if (! $tokens) {
            return;
        }

        ['judul' => $judul, 'isi' => $isi, 'data' => $data] = $notification->toFcm($notifiable);

        foreach ($tokens as $token) {
            if ($this->fcm->kirim($token, $judul, $isi, $data) === Fcm::TOKEN_MATI) {
                PerangkatAdmin::where('fcm_token', $token)->delete(); // aplikasi dihapus / token kadaluarsa
            }
        }
    }
}
