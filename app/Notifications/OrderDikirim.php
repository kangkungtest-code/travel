<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDikirim extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Order :number has shipped', ['number' => $this->order->nomor]))
            ->greeting(__('Hi, :name', ['name' => $notifiable->nama_lengkap]))
            ->line(__('Your order :number is on its way.', ['number' => $this->order->nomor]))
            ->line(__('Tracking number').': **'.$this->order->resi.'**')
            ->action(__('View order'), route('akun.pesanan.show', $this->order));
    }
}
