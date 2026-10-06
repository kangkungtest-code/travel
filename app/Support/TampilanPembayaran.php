<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\MetodePembayaran;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** Bagian pembayaran di halaman order pembeli. */
class TampilanPembayaran
{
    public static function untuk(Order $o): array
    {
        $bisaBayar = $o->status === Order::STATUS_MENUNGGU_PEMBAYARAN && ! $o->kadaluarsa_pada?->isPast();

        /** @var Payment|null $aktif */
        $aktif = $bisaBayar
            ? $o->payments()->where('status', Payment::PENDING)->whereNotNull('data_bayar')->latest()->first()
            : null;
        $aktif = $aktif?->masihBerlaku() ? $aktif : null;

        return [
            'bisa_bayar' => $bisaBayar,
            'ada_gateway' => MetodePembayaran::adaYangAktif(),
            'pilihan' => $bisaBayar ? MetodePembayaran::pilihan($o) : [],
            'bank' => \App\Support\AkunPembayaran::bankVa(),
            'aktif' => $aktif ? self::tagihan($aktif) : null,
            'simulasi' => $aktif && self::bisaSimulasi($aktif) ? [
                'webhook' => \App\Http\Controllers\WebhookPembayaranController::terakhir('xendit'),
            ] : null,
            'lunas' => $o->payments()->where('status', Payment::BERHASIL)->latest('dibayar_pada')->first()?->only(['gateway', 'mata_uang', 'jumlah']),
        ];
    }

    private static function bisaSimulasi(Payment $p): bool
    {
        $g = MetodePembayaran::gateway($p->gateway);

        return $g instanceof \App\Payments\XenditGateway && $g->bisaSimulasi() && $p->transaksi_id_eksternal;
    }

    private static function tagihan(Payment $p): array
    {
        $data = $p->data_bayar ?? [];

        return [
            'gateway' => $p->gateway,
            'label' => MetodePembayaran::label($p->gateway),
            'jumlah' => app(Kurs::class)->formatNilai((float) $p->jumlah, $p->mata_uang),
            'qr_svg' => isset($data['qr_string']) ? self::qr($data['qr_string']) : null,
            'bank' => $data['bank'] ?? null,
            'nomor_va' => $data['nomor_va'] ?? null,
        ];
    }

    public static function qr(string $isi): string
    {
        $opsi = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'addQuietzone' => true,
            'svgUseFillAttributes' => true,
        ]);

        return (new QRCode($opsi))->render($isi);
    }
}
