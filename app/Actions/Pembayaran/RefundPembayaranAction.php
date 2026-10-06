<?php

namespace App\Actions\Pembayaran;

use App\Exceptions\TokoException;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\GatewayException;
use App\Payments\MetodePembayaran;

/** Refund penuh pembayaran order lewat API gateway (dipakai saat retur diselesaikan dengan refund). */
class RefundPembayaranAction
{
    public function execute(Order $order, string $alasan): Payment
    {
        $payment = $order->payments()->where('status', Payment::BERHASIL)->latest('dibayar_pada')->first()
            ?? throw new TokoException('Order ini tidak punya pembayaran online yang bisa direfund. Lakukan refund manual.');

        $gateway = MetodePembayaran::gateway($payment->gateway)
            ?? throw new TokoException('Gateway pembayaran belum dikonfigurasi. Lakukan refund manual.');

        try {
            $refundId = $gateway->refund($payment, $alasan);
        } catch (GatewayException $e) {
            $payment->update(['catatan' => mb_substr('Refund otomatis gagal: '.$e->getMessage(), 0, 500)]);

            throw new TokoException('Refund otomatis gagal ('.MetodePembayaran::label($payment->gateway).'). Lakukan refund manual. Detail tersimpan di catatan pembayaran.');
        }

        $payment->update(['status' => Payment::DIREFUND, 'refund_id' => $refundId]);

        return $payment;
    }
}
