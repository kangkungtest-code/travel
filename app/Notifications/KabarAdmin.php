<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi untuk staff (aplikasi admin): disimpan di kotak masuk (database)
 * dan dikirim ke HP lewat FCM. Dibuat oleh App\Support\NotifikasiAdmin.
 *
 * $tujuan menunjuk layar di aplikasi: ['jenis' => 'pesanan'|'retur'|'stok', 'id' => ...].
 */
class KabarAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $jenis,
        public string $judul,
        public string $isi,
        public array $tujuan = [],
    ) {
        // Baru dikirim setelah transaksi database selesai (order/stok benar-benar tersimpan).
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->jenis;
    }

    public function toArray(object $notifiable): array
    {
        return ['jenis' => $this->jenis, 'judul' => $this->judul, 'isi' => $this->isi, 'tujuan' => $this->tujuan];
    }

    /** @return array{judul: string, isi: string, data: array<string, string>} */
    public function toFcm(object $notifiable): array
    {
        return [
            'judul' => $this->judul,
            'isi' => $this->isi,
            'data' => [
                'jenis' => $this->jenis,
                'notifikasi_id' => (string) $this->id,
                'tujuan_jenis' => (string) ($this->tujuan['jenis'] ?? ''),
                'tujuan_id' => (string) ($this->tujuan['id'] ?? ''),
            ],
        ];
    }
}
