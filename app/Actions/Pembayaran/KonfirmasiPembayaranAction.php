<?php

namespace App\Actions\Pembayaran;

use App\Actions\Order\UbahStatusOrderAction;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Payments\HasilWebhook;
use App\Payments\MetodePembayaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hasil dari gateway (webhook atau kembali dari PayPal) -> status Payment & Order.
 * Aman dipanggil berkali-kali untuk transaksi yang sama (webhook bisa dikirim ulang).
 */
class KonfirmasiPembayaranAction
{
    public function __construct(private UbahStatusOrderAction $ubahStatus) {}

    public function execute(HasilWebhook $hasil): ?Payment
    {
        if ($hasil->status === 'abaikan') {
            return null;
        }

        $payment = $this->cari($hasil);
        if (! $payment) {
            Log::warning('Notifikasi pembayaran untuk transaksi yang tidak dikenal', ['payment' => $hasil->paymentId, 'transaksi' => $hasil->transaksiId]);

            return null;
        }

        return DB::transaction(function () use ($payment, $hasil) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (in_array($payment->status, [Payment::BERHASIL, Payment::DIREFUND], true)) {
                return $payment; // sudah diproses
            }

            if ($hasil->status !== 'berhasil') {
                if ($payment->status === Payment::PENDING) {
                    $payment->update(['status' => $hasil->status === 'kadaluarsa' ? Payment::KADALUARSA : Payment::GAGAL, 'raw_payload' => $hasil->raw]);
                }

                return $payment;
            }

            $cocok = $hasil->mataUang === $payment->mata_uang
                && $hasil->jumlah !== null
                && $hasil->jumlah + 0.01 >= (float) $payment->jumlah;

            $payment->update([
                'status' => Payment::BERHASIL,
                'dibayar_pada' => now(),
                'id_capture' => $hasil->idCapture ?? $payment->id_capture,
                'transaksi_id_eksternal' => $payment->transaksi_id_eksternal ?? $hasil->transaksiId,
                'raw_payload' => $hasil->raw,
                'catatan' => $cocok ? null : "Jumlah diterima {$hasil->mataUang} {$hasil->jumlah} tidak sama dengan tagihan {$payment->mata_uang} {$payment->jumlah}",
            ]);

            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $metode = MetodePembayaran::label($payment->gateway);

            if ($cocok && $order->status === Order::STATUS_MENUNGGU_PEMBAYARAN) {
                $this->ubahStatus->execute($order, Order::STATUS_DIBAYAR, null, ['catatan' => "Dibayar via {$metode}"]);
            } else {
                // Pembayaran masuk tapi order sudah kadaluarsa/batal, atau jumlahnya tidak cocok: admin perlu turun tangan.
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'dari' => $order->status,
                    'ke' => $order->status,
                    'catatan' => $cocok
                        ? "Pembayaran {$metode} masuk setelah order {$order->status} — perlu refund atau diproses manual"
                        : "Pembayaran {$metode} masuk dengan jumlah tidak cocok — cek manual",
                ]);
                Log::warning("Pembayaran {$payment->id} untuk order {$order->nomor} perlu ditinjau admin.");
                \App\Support\NotifikasiAdmin::pembayaranPerluDicek($order, $cocok
                    ? "Pembayaran {$metode} masuk setelah order {$order->status}"
                    : "Pembayaran {$metode} masuk dengan jumlah tidak cocok");
            }

            return $payment;
        });
    }

    private function cari(HasilWebhook $hasil): ?Payment
    {
        if ($hasil->paymentId && $p = Payment::query()->find($hasil->paymentId)) {
            return $p;
        }

        return $hasil->transaksiId
            ? Payment::query()->where('transaksi_id_eksternal', $hasil->transaksiId)->first()
            : null;
    }
}
