<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;

/** Data order untuk halaman akun pembeli. Nominal ditampilkan dalam mata uang order (snapshot). */
class TampilanOrder
{
    public static function labelStatus(string $status, bool $sewa = false): string
    {
        if ($sewa) {
            return match ($status) {
                Order::STATUS_DIPROSES => __('Confirmed'),
                Order::STATUS_DIKIRIM => __('Vehicle in use'),
                default => self::labelStatus($status),
            };
        }

        return match ($status) {
            Order::STATUS_MENUNGGU_PEMBAYARAN => __('Awaiting payment'),
            Order::STATUS_DIBAYAR => __('Paid'),
            Order::STATUS_DIPROSES => __('Being packed'),
            Order::STATUS_DIKIRIM => __('Shipped'),
            Order::STATUS_SELESAI => __('Completed'),
            Order::STATUS_KADALUARSA => __('Expired'),
            Order::STATUS_DIBATALKAN => __('Cancelled'),
            default => $status,
        };
    }

    private static function uang(Order $o, float $nilai): string
    {
        return app(Kurs::class)->formatNilai($nilai, $o->mata_uang);
    }

    public static function ringkas(Order $o): array
    {
        return [
            'nomor' => $o->nomor,
            'url' => route('akun.pesanan.show', $o),
            'tanggal' => $o->created_at->timezone(config('toko.zona_waktu'))->locale(str_replace('_', '-', app()->getLocale()))->isoFormat('D MMM YYYY'),
            'status' => $o->status,
            'label_status' => self::labelStatus($o->status, $o->sumber_order === 'sewa'),
            'total' => self::uang($o, (float) $o->total),
        ];
    }

    /**
     * Pelacak progres untuk order yang sudah dibayar: 4 langkah + pesan
     * "apa yang terjadi sekarang". Null untuk order yang belum/tidak dibayar.
     */
    public static function progres(Order $o): ?array
    {
        $urutan = [Order::STATUS_DIBAYAR, Order::STATUS_DIPROSES, Order::STATUS_DIKIRIM, Order::STATUS_SELESAI];
        $posisi = array_search($o->status, $urutan, true);
        if ($posisi === false) {
            return null;
        }

        $zona = config('toko.zona_waktu');
        $locale = str_replace('_', '-', app()->getLocale());
        $waktu = $o->statusHistories->groupBy('ke')->map(fn ($h) => $h->last()->created_at);
        $fmt = fn ($t) => $t?->timezone($zona)->locale($locale)->isoFormat('D MMM, HH:mm');

        $sewa = $o->sumber_order === 'sewa';
        $label = collect($urutan)->mapWithKeys(fn ($st) => [$st => self::labelStatus($st, $sewa)])->all();

        $pesan = $sewa ? match ($o->status) {
            Order::STATUS_DIBAYAR => [__('Payment received'), __('Thanks! We\'ll check your details and confirm the booking shortly.')],
            Order::STATUS_DIPROSES => [__('Booking confirmed'), __('Your vehicle is reserved. Bring your ID and driving licence at pick-up.')],
            Order::STATUS_DIKIRIM => [__('Enjoy your trip'), __('Please return the vehicle on time. Contact us if your plans change.')],
            Order::STATUS_SELESAI => [__('Rental completed'), __('Thanks for renting with us!')],
        } : match ($o->status) {
            Order::STATUS_DIBAYAR => [__('Payment received'), __('Thanks! We\'ll start packing your order soon. You\'ll get an email when it ships.')],
            Order::STATUS_DIPROSES => [__('We\'re packing your order'), __('Your items are being prepared. You\'ll get the tracking number as soon as it ships.')],
            Order::STATUS_DIKIRIM => [__('Your order is on its way'), __('Use the tracking number below on the courier\'s website to follow the package.')],
            Order::STATUS_SELESAI => [__('Order completed'), __('Thanks for shopping with us!')],
        };

        $lunas = $o->payments->firstWhere('status', \App\Models\Payment::BERHASIL);

        return [
            'judul' => $pesan[0],
            'teks' => $pesan[1],
            'langkah' => collect($urutan)->map(fn ($st, $i) => [
                'label' => $label[$st],
                'waktu' => $i <= $posisi ? $fmt($waktu[$st] ?? null) : null,
                'keadaan' => $i < $posisi ? 'lewat' : ($i === $posisi ? 'sekarang' : 'nanti'),
            ])->all(),
            'resi' => $sewa ? null : $o->resi,
            'dibayar' => $lunas ? __('Paid :amount via :method on :date', [
                'amount' => app(Kurs::class)->formatNilai((float) $lunas->jumlah, $lunas->mata_uang),
                'method' => \App\Payments\MetodePembayaran::label($lunas->gateway),
                'date' => $fmt($lunas->dibayar_pada ?? $o->dibayar_pada),
            ]) : null,
        ];
    }

    public static function detail(Order $o): array
    {
        $o->loadMissing('items.variant.product.images', 'statusHistories', 'payments', 'bookingSewa.tipe.foto', 'bookingSewa.lokasi', 'bookingSewa.unit');
        $sewa = $o->bookingSewa;
        $kurs = app(Kurs::class);
        $locale = app()->getLocale();

        return self::ringkas($o) + [
            'items' => $o->items->map(function (OrderItem $i) use ($o, $kurs, $locale) {
                $hargaSatuan = $kurs->konversiDenganRate((float) $i->harga_saat_itu, (float) $o->kurs_terpakai, $o->mata_uang);

                return [
                    'nama' => $i->variant?->product?->getTranslation('nama_terjemahan', $locale) ?? '—',
                    'opsi' => collect($i->variant?->opsi ?? [])->map(fn ($n, $k) => __($k).': '.__($n))->values()->implode(', '),
                    'foto' => $i->variant?->product?->fotoUntuk($i->variant->opsi[\App\Models\Product::OPSI_WARNA] ?? null)?->thumbUrl(),
                    'qty' => $i->qty,
                    'harga' => self::uang($o, $hargaSatuan),
                    'total' => self::uang($o, $hargaSatuan * $i->qty),
                ];
            })->all(),
            'subtotal' => self::uang($o, (float) $o->subtotal),
            'ongkir' => self::uang($o, (float) $o->ongkir),
            'mata_uang' => $o->mata_uang,
            'alamat' => $o->alamat_snapshot ?? [],
            'nama_negara' => __(config('toko.negara.'.($o->alamat_snapshot['negara'] ?? ''), $o->alamat_snapshot['negara'] ?? '')),
            'berat_kg' => number_format($o->berat_gram / 1000, 2),
            'batas_bayar' => $o->kadaluarsa_pada?->timezone(config('toko.zona_waktu'))->locale(str_replace('_', '-', $locale))->isoFormat('D MMM YYYY, HH:mm').' '.$o->kadaluarsa_pada?->timezone(config('toko.zona_waktu'))->format('T'),
            'menunggu' => $o->status === Order::STATUS_MENUNGGU_PEMBAYARAN,
            'resi' => $o->resi,
            'riwayat' => $o->statusHistories->map(fn ($h) => [
                'label' => self::labelStatus($h->ke, (bool) $sewa),
                'waktu' => $h->created_at->timezone(config('toko.zona_waktu'))->locale(str_replace('_', '-', $locale))->isoFormat('D MMM YYYY, HH:mm'),
            ])->all(),
            'pembayaran' => TampilanPembayaran::untuk($o),
            'progres' => self::progres($o),
            'bisa_retur' => ! $sewa && \App\Support\Fitur::aktif('retur') && TampilanRetur::bisaDiajukan($o),
            'sewa' => $sewa ? self::sewa($o, $sewa) : null,
            'batas_retur_hari' => config('toko.retur.batas_hari'),
            'retur' => ($r = $o->returnRequests()->latest()->first()) ? [
                'model' => $r,
                'label' => TampilanRetur::labelStatus($r->status),
                'status' => $r->status,
                'penjelasan' => TampilanRetur::penjelasan($r),
                'catatan_admin' => $r->catatan_admin,
                'resi_kembali' => $r->resi_kembali,
                'alamat_retur' => \App\Models\Pengaturan::ambil('retur.alamat') ?? config('toko.retur.alamat'),
            ] : null,
        ];
    }

    /** Detail booking sewa untuk halaman pesanan pembeli. */
    public static function sewa(Order $o, \App\Models\BookingSewa $b): array
    {
        $r = $b->rincian ?? [];
        $nama = $b->tipe?->nama() ?? ($r['nama_kendaraan'][app()->getLocale()] ?? $r['nama_kendaraan']['en'] ?? '—');
        $tampil = $o->status === Order::STATUS_MENUNGGU_PEMBAYARAN || $o->status === Order::STATUS_KADALUARSA || $o->status === Order::STATUS_DIBATALKAN;

        return [
            'nama' => $nama,
            'foto' => $b->tipe?->foto->first()?->thumbUrl(),
            'mode' => Spesifikasi::label('mode', $b->mode),
            'mulai' => TampilanKendaraan::waktu($b->mulai),
            'selesai' => TampilanKendaraan::waktu($b->selesai),
            'durasi' => TampilanKendaraan::durasi((int) ($r['hari'] ?? 0), (int) ($r['sisa_jam'] ?? 0)),
            'lokasi' => $b->lokasi ? $b->lokasi->nama().' — '.$b->lokasi->kota : null,
            'alamat_lokasi' => $b->lokasi?->alamat,
            'peta' => $b->lokasi?->url_peta,
            'jam_lokasi' => $b->lokasi?->jam_operasional,
            // Plat baru ditunjukkan setelah dibayar.
            'unit' => $tampil ? null : $b->unit?->plat_nomor,
            'penyewa' => $b->nama_penyewa.' · '.$b->telepon,
            'catatan' => $b->catatan,
            'bbm' => (bool) ($r['termasuk_bbm'] ?? false),
            'musim' => $r['musim'] ?? [],
        ];
    }
}
