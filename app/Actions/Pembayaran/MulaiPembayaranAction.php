<?php

namespace App\Actions\Pembayaran;

use App\Exceptions\TokoException;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\GatewayException;
use App\Payments\MetodePembayaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Pembeli memilih metode bayar untuk order `menunggu_pembayaran`. */
class MulaiPembayaranAction
{
    public function execute(Order $order, string $metode, array $opsi = []): Payment
    {
        $gateway = (MetodePembayaran::ditawarkan($metode) ? MetodePembayaran::gateway($metode) : null) ?? throw new TokoException(__('This payment method is not available.'));
        $mataUang = $gateway->mataUangUntuk($order);
        $tagihan = $mataUang ? MetodePembayaran::tagihan($order, $mataUang) : null;
        if (! $tagihan) {
            throw new TokoException(__('This payment method is not available.'));
        }

        $payment = DB::transaction(function () use ($order, $metode, $opsi, $mataUang, $tagihan) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== Order::STATUS_MENUNGGU_PEMBAYARAN || $order->kadaluarsa_pada?->isPast()) {
                throw new TokoException(__('This order can no longer be paid.'));
            }

            // Pakai lagi tagihan yang masih berlaku untuk metode & bank yang sama.
            $ada = $order->payments()->where('status', Payment::PENDING)->where('gateway', $metode)->latest()->first();
            if ($ada && $ada->masihBerlaku() && ($ada->data_bayar['bank'] ?? null) === ($opsi['bank'] ?? null) && ($ada->url_bayar || $ada->data_bayar)) {
                return $ada;
            }

            // Tagihan lain yang belum dibayar diganti.
            $order->payments()->where('status', Payment::PENDING)->update(['status' => Payment::KADALUARSA, 'catatan' => 'Diganti metode lain']);

            return $order->payments()->create([
                'gateway' => $metode,
                'status' => Payment::PENDING,
                'mata_uang' => $mataUang,
                'jumlah' => $tagihan['jumlah'],
                'kurs_terpakai' => $tagihan['kurs'],
                'jumlah_idr' => $tagihan['jumlah_idr'],
                'kadaluarsa_pada' => $order->kadaluarsa_pada,
            ]);
        });

        if ($payment->url_bayar || $payment->data_bayar) {
            return $payment;
        }

        // Panggilan ke gateway di luar transaksi supaya kunci baris tidak tertahan lama.
        try {
            $gateway->buat($payment, $order, $opsi);
            $payment->save();
        } catch (GatewayException $e) {
            Log::error("Gagal membuat tagihan {$metode} untuk {$order->nomor}: ".$e->getMessage());
            $payment->update(['status' => Payment::GAGAL, 'catatan' => mb_substr($e->getMessage(), 0, 500)]);

            throw new TokoException(__('We couldn\'t start this payment. Please try again or choose another method.'));
        }

        return $payment;
    }
}
