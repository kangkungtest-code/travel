<?php

namespace App\Notifications;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Filament\Support\LabelAdmin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Payments\MetodePembayaran;
use App\Support\Kurs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email ringkas untuk pemilik toko (bahasa Indonesia), dikirim ke alamat yang diatur di
 * Pengaturan → Kontak & notifikasi. Isi: rincian pesanan / retur + tombol ke panel admin.
 */
class EmailPemilik extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $jenis,
        public string $judul,
        public string $isi,
        public ?Order $order = null,
        public ?ReturnRequest $retur = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('['.config('toko.nama').'] '.$this->judul)
            ->greeting($this->judul)
            ->line($this->isi);

        if ($o = $this->order?->fresh(['items.variant.product', 'payments'])) {
            $mail->line('**Barang**');
            foreach ($o->items as $i) {
                /** @var OrderItem $i */
                $nama = $i->variant?->product?->getTranslation('nama_terjemahan', 'id') ?? 'Produk terhapus';
                $opsi = collect($i->variant?->opsi ?? [])->values()->implode(' / ');
                $mail->line("- {$nama}".($opsi ? " ({$opsi})" : '')." × {$i->qty} — ".LabelAdmin::rupiah($i->qty * (float) $i->harga_saat_itu));
            }

            $a = $o->alamat_snapshot ?? [];
            $lunas = $o->payments->firstWhere('status', \App\Models\Payment::BERHASIL);
            $mail->line('**Ongkir:** '.LabelAdmin::rupiah($o->ongkir_idr).' · **Total:** '.LabelAdmin::rupiah($o->total_idr)
                    .($o->mata_uang !== 'IDR' ? ' (pembeli melihat '.app(Kurs::class)->formatNilai((float) $o->total, $o->mata_uang).')' : ''))
                ->line('**Status:** '.(LabelAdmin::STATUS_ORDER[$o->status] ?? $o->status)
                    .($lunas ? ' · dibayar lewat '.MetodePembayaran::label($lunas->gateway) : ''))
                ->line('**Kirim ke:** '.trim(($a['nama_penerima'] ?? '').', '.($a['telepon'] ?? '')
                    .' — '.($a['detail_alamat'] ?? '').', '.($a['kota'] ?? '').' '.($a['kode_pos'] ?? '').', '
                    .config('toko.negara.'.($a['negara'] ?? ''), $a['negara'] ?? '')))
                ->action('Buka di panel admin', OrderResource::getUrl('view', ['record' => $o], panel: 'admin'));
        } elseif ($r = $this->retur) {
            $mail->action('Buka retur di panel admin', ReturnRequestResource::getUrl('view', ['record' => $r], panel: 'admin'));
        }

        return $mail->salutation('— Sistem '.config('toko.nama'));
    }
}
