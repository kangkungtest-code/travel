<?php

namespace App\Support;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\KabarAdmin;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Kabar ke staff yang punya izin terkait (aplikasi admin + kotak masuk).
 * Gagal mengirim kabar tidak boleh menggagalkan order/pembayaran, jadi semua error ditelan & dicatat.
 */
class NotifikasiAdmin
{
    public const PESANAN_BARU = 'pesanan_baru';
    public const PESANAN_DIBAYAR = 'pesanan_dibayar';
    public const PEMBAYARAN_DICEK = 'pembayaran_perlu_dicek';
    public const RETUR_DIAJUKAN = 'retur_diajukan';
    public const RESI_RETUR = 'resi_retur';
    public const STOK_MENIPIS = 'stok_menipis';

    /** Stok tersedia segini atau kurang dianggap menipis (sama dengan panel). */
    public const BATAS_MENIPIS = 5;

    public static function pesananBaru(Order $o): void
    {
        self::kirim('order.lihat', new KabarAdmin(self::PESANAN_BARU,
            "Pesanan baru {$o->nomor}",
            self::rupiah($o).' · menunggu pembayaran',
            ['jenis' => 'pesanan', 'id' => $o->nomor],
        ));
        EmailPemilik::kirim(self::PESANAN_BARU, "Pesanan baru {$o->nomor}", 'Pesanan dibuat dan menunggu pembayaran ('.self::rupiah($o).').', $o);
    }

    public static function pesananDibayar(Order $o, ?User $oleh = null): void
    {
        self::kirim('order.ubah_status', new KabarAdmin(self::PESANAN_DIBAYAR,
            "{$o->nomor} dibayar",
            self::rupiah($o).' · siap dikemas',
            ['jenis' => 'pesanan', 'id' => $o->nomor],
        ), $oleh);
        EmailPemilik::kirim(self::PESANAN_DIBAYAR, "Pesanan {$o->nomor} dibayar", 'Pembayaran sudah masuk. Pesanan siap dikemas.', $o);
    }

    public static function pembayaranPerluDicek(Order $o, string $alasan): void
    {
        self::kirim('order.ubah_status', new KabarAdmin(self::PEMBAYARAN_DICEK,
            "Pembayaran {$o->nomor} perlu dicek",
            $alasan,
            ['jenis' => 'pesanan', 'id' => $o->nomor],
        ));
        EmailPemilik::kirim(self::PEMBAYARAN_DICEK, "Pembayaran {$o->nomor} perlu dicek", $alasan.'. Cek riwayat pesanan di panel admin.', $o);
    }

    public static function returDiajukan(ReturnRequest $r): void
    {
        self::kirim('retur.kelola', new KabarAdmin(self::RETUR_DIAJUKAN,
            "Retur baru {$r->order->nomor}",
            \Illuminate\Support\Str::limit($r->alasan, 120),
            ['jenis' => 'retur', 'id' => $r->id],
        ));
        EmailPemilik::kirim(self::RETUR_DIAJUKAN, "Retur baru untuk {$r->order->nomor}", 'Alasan pembeli: '.$r->alasan, null, $r);
    }

    public static function resiReturDiisi(ReturnRequest $r): void
    {
        self::kirim('retur.kelola', new KabarAdmin(self::RESI_RETUR,
            "Resi retur {$r->order->nomor}",
            "Pembeli mengirim balik barang: {$r->resi_kembali}",
            ['jenis' => 'retur', 'id' => $r->id],
        ));
    }

    /** @param  iterable<ProductVariant>  $varian  varian yang stok tersedianya baru saja turun */
    public static function cekStokMenipis(iterable $varian): void
    {
        $menipis = collect($varian)
            ->map(fn (ProductVariant $v) => [$v, $v->stokTersedia()])
            ->filter(fn ($x) => $x[1] <= self::BATAS_MENIPIS);

        if ($menipis->isEmpty()) {
            return;
        }

        $daftar = $menipis->map(fn ($x) => self::namaVarian($x[0]).' tinggal '.$x[1])->implode(', ');

        self::kirim('stok.edit', new KabarAdmin(self::STOK_MENIPIS,
            $menipis->count() === 1 ? 'Stok menipis' : "{$menipis->count()} varian stoknya menipis",
            \Illuminate\Support\Str::limit($daftar, 200),
            ['jenis' => 'stok', 'id' => $menipis->first()[0]->id],
        ));
    }

    public static function namaVarian(ProductVariant $v): string
    {
        $nama = $v->product?->getTranslation('nama_terjemahan', 'id') ?? $v->sku;
        $opsi = collect($v->opsi ?? [])->values()->implode(' ');

        return trim("{$nama} {$opsi}");
    }

    private static function rupiah(Order $o): string
    {
        return 'Rp'.number_format((float) $o->total_idr, 0, ',', '.');
    }

    private static function kirim(string $izin, KabarAdmin $kabar, ?User $kecuali = null): void
    {
        try {
            $penerima = User::permission($izin)->get()
                ->reject(fn (User $u) => $kecuali && $u->is($kecuali));

            if ($penerima->isNotEmpty()) {
                Notification::send($penerima, $kabar);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
