<?php

namespace App\Notifications;

use App\Models\ReturnRequest;
use App\Support\TampilanRetur;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturDiperbarui extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ReturnRequest $retur) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->retur->fresh('order');
        $mail = (new MailMessage)
            ->subject(__('Return for order :number: :status', ['number' => $r->order->nomor, 'status' => TampilanRetur::labelStatus($r->status)]))
            ->greeting(__('Hi, :name', ['name' => $notifiable->nama_lengkap]))
            ->line(TampilanRetur::penjelasan($r));

        if (filled($r->catatan_admin)) {
            $mail->line(__('Note from the store').': '.$r->catatan_admin);
        }

        return $mail->action(__('View order'), route('akun.pesanan.show', $r->order));
    }
}
