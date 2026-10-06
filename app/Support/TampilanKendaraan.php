<?php

namespace App\Support;

use App\Models\FotoKendaraan;
use App\Models\Lokasi;
use App\Models\Tarif;
use App\Models\TipeKendaraan;

/** Data tampilan kendaraan untuk storefront (kartu, detail, rincian harga). */
class TampilanKendaraan
{
    /** Butuh relasi foto & tarif ter-load; `unit_bebas_count` kalau pencarian lengkap. */
    public static function kartu(TipeKendaraan $t, ?PencarianSewa $cari = null): array
    {
        $tarif = $cari ? $t->tarifUntuk($cari->mode) : null;
        $rincian = ($cari?->lengkap() && $tarif) ? HargaSewa::hitung($tarif, $cari->mulai, $cari->selesai) : null;
        $hargaMulai = $tarif?->harga_harian ?? $t->hargaMulai();

        return [
            'nama' => $t->nama(),
            'url' => route('sewa.show', ['kendaraan' => $t] + ($cari?->query() ?? [])),
            'foto' => $t->foto->first()?->thumbUrl(),
            'foto_semua' => $t->foto->take(4)->map(fn (FotoKendaraan $f) => $f->thumbUrl())->all(),
            'ringkasan' => $t->ringkasan(),
            'mulai_dari' => $hargaMulai ? TampilanProduk::harga((float) $hargaMulai) : null,
            'total' => $rincian ? TampilanProduk::harga($rincian['total']) : null,
            'durasi' => $rincian ? self::durasi($rincian['hari'], $rincian['sisa_jam']) : null,
            'tersedia' => $rincian ? (int) ($t->unit_bebas_count ?? 0) > 0 : null,
            'mode' => $t->tarif->where('is_active', true)->pluck('mode')->map(fn ($m) => Spesifikasi::label('mode', $m))->values()->all(),
        ];
    }

    public static function durasi(int $hari, int $jam): string
    {
        return collect([
            $hari ? trans_choice('{1} :count day|[2,*] :count days', $hari) : null,
            $jam ? trans_choice('{1} :count hour|[2,*] :count hours', $jam) : null,
        ])->filter()->implode(' ');
    }

    /** Rincian harga untuk ditampilkan (mata uang pembeli). */
    public static function rincian(array $r): array
    {
        $baris = [];
        if ($r['hari'] > 0) {
            $baris[] = [trans_choice('{1} :count day|[2,*] :count days', $r['hari']).' × '.TampilanProduk::harga($r['harga_hari']), TampilanProduk::harga($r['hari'] * $r['harga_hari'])];
        }
        if ($r['sisa_jam'] > 0) {
            $baris[] = [__('Extra :hours', ['hours' => trans_choice('{1} :count hour|[2,*] :count hours', $r['sisa_jam'])]), TampilanProduk::harga($r['harga_sisa'])];
        }
        if ($r['tambahan_musim'] > 0) {
            $baris[] = [__('Peak season (:name)', ['name' => implode(', ', $r['musim'])]), '+'.TampilanProduk::harga($r['tambahan_musim'])];
        }

        return [
            'baris' => $baris,
            'total' => TampilanProduk::harga($r['total']),
            'durasi' => self::durasi($r['hari'], $r['sisa_jam']),
            'minimal' => $r['jam_ditagih'] > $r['durasi_jam'] ? __('Minimum rental is :hours hours.', ['hours' => $r['jam_ditagih']]) : null,
            'bbm' => $r['termasuk_bbm'],
        ];
    }

    /** Waktu dalam zona toko, mis. "Sen, 2 Nov 2026 09.00". */
    public static function waktu(\DateTimeInterface $w): string
    {
        return \Carbon\CarbonImmutable::instance($w)->setTimezone(config('toko.zona_waktu'))->locale(app()->getLocale())->isoFormat('ddd, D MMM YYYY HH:mm');
    }

    /** @return array<int, array{id:string, nama:string}> */
    public static function lokasi(): array
    {
        return Lokasi::aktif()->get()->map(fn (Lokasi $l) => ['id' => $l->id, 'nama' => $l->nama().' — '.$l->kota])->all();
    }

    /** @return array<string, string> mode => label */
    public static function pilihanMode(): array
    {
        return collect(config('travel.mode'))->keys()->mapWithKeys(fn ($m) => [$m => Spesifikasi::label('mode', $m)])->all();
    }

    public static function modeTarif(TipeKendaraan $t, string $mode): ?Tarif
    {
        return $t->tarifUntuk($mode);
    }
}
