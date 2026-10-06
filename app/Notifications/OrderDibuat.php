<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\TampilanOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Konfirmasi order + batas bayar. Bahasa mengikuti preferensi pembeli (User::preferredLocale). */
class OrderDibuat extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = TampilanOrder::detail($this->order->fresh());

        $mail = (new MailMessage)
            ->subject(__('Order :number received', ['number' => $o['nomor']]))
            ->greeting(__('Hi, :name', ['name' => $notifiable->nama_lengkap]))
            ->line(__('Thanks for your order. Here is a summary:'));

        foreach ($o['items'] as $i) {
            $mail->line("{$i['nama']} ({$i['opsi']}) × {$i['qty']}: {$i['total']}");
        }

        return $mail
            ->line(__('Shipping').': '.$o['ongkir'])
            ->line('**'.__('Total').': '.$o['total'].'**')
            ->line(__('Please pay by :deadline. Unpaid orders are cancelled automatically.', ['deadline' => $o['batas_bayar']]))
            ->action(__('View order'), $o['url']);
    }
}
