<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** Gateway siap dipakai (kredensial sudah diisi). */
    public function aktif(): bool;

    /** Mata uang yang akan ditagihkan untuk order ini (null = tidak bisa). */
    public function mataUangUntuk(Order $order): ?string;

    /**
     * Buat tagihan di gateway dan isi Payment (transaksi_id_eksternal, url_bayar / data_bayar).
     *
     * @param  array<string, mixed>  $opsi  mis. ['bank' => 'BCA']
     *
     * @throws GatewayException
     */
    public function buat(Payment $payment, Order $order, array $opsi = []): void;

    /**
     * Verifikasi & baca webhook. Melempar WebhookTidakSah kalau tanda tangan/token salah.
     */
    public function bacaWebhook(Request $request): HasilWebhook;

    /** @throws GatewayException kalau refund ditolak / tidak didukung channel ini */
    public function refund(Payment $payment, string $alasan): string;
}
