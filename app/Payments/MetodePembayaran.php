<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Support\Kurs;

/**
 * Daftar metode bayar & jumlah tagihannya.
 *
 * Opsi (a): order disimpan dalam mata uang pilihan pembeli. Kalau metode bayar butuh
 * mata uang lain (PayPal: USD/TWD, Xendit: IDR), tagihan dihitung ulang dengan kurs saat ini
 * dan ditampilkan ke pembeli sebelum ia membayar.
 */
class MetodePembayaran
{
    /** @return array<string, PaymentGateway> */
    public static function semua(): array
    {
        return [
            Payment::GATEWAY_PAYPAL => app(PayPalGateway::class),
            Payment::GATEWAY_QRIS => new XenditGateway('qris'),
            Payment::GATEWAY_VA => new XenditGateway('va'),
        ];
    }

    public static function gateway(string $kode): ?PaymentGateway
    {
        $g = self::semua()[$kode] ?? null;

        return $g && $g->aktif() ? $g : null;
    }

    /**
     * Boleh dipilih pembeli: akunnya lengkap DAN tidak dimatikan pemilik di panel.
     * (gateway() tetap mengembalikan metode yang dimatikan supaya webhook & refund
     * transaksi lama tetap jalan.)
     */
    public static function ditawarkan(string $kode): bool
    {
        return self::gateway($kode) !== null && \App\Support\AkunPembayaran::metodeNyala($kode)
            && ($kode !== Payment::GATEWAY_PAYPAL || \App\Support\Fitur::aktif('paypal'))
            && ($kode !== Payment::GATEWAY_VA || \App\Support\AkunPembayaran::bankVa() !== []);
    }

    public static function adaYangAktif(): bool
    {
        return collect(array_keys(self::semua()))->contains(fn (string $kode) => self::ditawarkan($kode));
    }

    public static function label(string $kode): string
    {
        return match ($kode) {
            Payment::GATEWAY_PAYPAL => 'PayPal',
            Payment::GATEWAY_QRIS => 'QRIS',
            Payment::GATEWAY_VA => __('Bank virtual account'),
            default => $kode,
        };
    }

    /**
     * Tagihan untuk order dalam mata uang tertentu.
     *
     * @return array{jumlah: float, kurs: float, jumlah_idr: float}|null null kalau kurs belum tersedia
     */
    public static function tagihan(Order $order, string $mataUang): ?array
    {
        $kurs = app(Kurs::class);
        $totalIdr = (float) $order->total_idr;

        if ($mataUang === $order->mata_uang) {
            return ['jumlah' => (float) $order->total, 'kurs' => (float) $order->kurs_terpakai, 'jumlah_idr' => $totalIdr];
        }

        if ($mataUang === config('toko.base_currency')) {
            return ['jumlah' => $totalIdr, 'kurs' => 1.0, 'jumlah_idr' => $totalIdr];
        }

        if ($kurs->mataUangEfektif($mataUang) !== $mataUang) {
            return null; // kurs belum diisi admin
        }

        // Dihitung per item seperti saat checkout, supaya pembulatannya konsisten.
        $order->loadMissing('items');
        $jumlah = $order->items->sum(fn (OrderItem $i) => $kurs->konversi((float) $i->harga_saat_itu, $mataUang) * $i->qty)
            + $kurs->konversi((float) $order->ongkir_idr, $mataUang);

        return [
            'jumlah' => round($jumlah, $kurs->desimal($mataUang)),
            'kurs' => (float) $kurs->rateEfektif($mataUang),
            'jumlah_idr' => $totalIdr,
        ];
    }

    /**
     * Pilihan untuk halaman order.
     *
     * @return array<int, array{kode: string, label: string, mata_uang: string, jumlah: float, tampil: string, beda_mata_uang: bool}>
     */
    public static function pilihan(Order $order): array
    {
        $kurs = app(Kurs::class);
        $hasil = [];

        foreach (self::semua() as $kode => $g) {
            if (! self::ditawarkan($kode) || ! ($mu = $g->mataUangUntuk($order)) || ! ($t = self::tagihan($order, $mu))) {
                continue;
            }

            $hasil[] = [
                'kode' => $kode,
                'label' => self::label($kode),
                'mata_uang' => $mu,
                'jumlah' => $t['jumlah'],
                'tampil' => $kurs->formatNilai($t['jumlah'], $mu),
                'beda_mata_uang' => $mu !== $order->mata_uang,
            ];
        }

        return $hasil;
    }
}
