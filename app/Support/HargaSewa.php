<?php

namespace App\Support;

use App\Models\Tarif;
use App\Models\TarifMusim;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Hitung harga sewa dari tarif & rentang waktu.
 *
 * - Durasi dibulatkan ke atas per jam, minimal `minimal_jam` tarif.
 * - Tiap 24 jam penuh = harga harian.
 * - Sisa jam = termurah dari: harga harian, harga 12 jam (kalau sisa ≤ 12 jam),
 *   harga per jam × sisa (kalau tarif per jam diisi).
 * - Musim ramai: tiap blok (24 jam / sisa) naik sesuai persen musim yang mencakup
 *   tanggal mulai blok itu (zona waktu toko). Kalau beberapa musim tumpang tindih,
 *   dipakai kenaikan terbesar.
 */
class HargaSewa
{
    /**
     * @return array{durasi_jam:int, jam_ditagih:int, hari:int, sisa_jam:int, harga_hari:float, harga_sisa:float,
     *               harga_dasar:float, tambahan_musim:float, total:float, musim:array<int,string>, termasuk_bbm:bool}
     */
    public static function hitung(Tarif $tarif, CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        $menit = $mulai->diffInMinutes($selesai, false);
        if ($menit <= 0) {
            throw new InvalidArgumentException('Waktu kembali harus setelah waktu ambil.');
        }

        $durasi = (int) ceil($menit / 60);
        $jam = max($durasi, (int) $tarif->minimal_jam);
        $hari = intdiv($jam, 24);
        $sisa = $jam % 24;

        $hargaSisa = 0.0;
        if ($sisa > 0) {
            $calon = [(float) $tarif->harga_harian];
            if ($tarif->harga_12jam && $sisa <= 12) {
                $calon[] = (float) $tarif->harga_12jam;
            }
            if ($tarif->harga_per_jam) {
                $calon[] = $sisa * (float) $tarif->harga_per_jam;
            }
            $hargaSisa = min($calon);
        }

        $zona = config('toko.zona_waktu', 'Asia/Makassar');
        $awal = CarbonImmutable::instance($mulai)->setTimezone($zona);
        $musimAktif = TarifMusim::query()
            ->where('is_active', true)
            ->whereDate('mulai', '<=', $awal->addHours($jam)->toDateString())
            ->whereDate('selesai', '>=', $awal->toDateString())
            ->get();

        $blok = [];
        for ($i = 0; $i < $hari; $i++) {
            $blok[] = [$awal->addHours(24 * $i), (float) $tarif->harga_harian];
        }
        if ($sisa > 0) {
            $blok[] = [$awal->addHours(24 * $hari), $hargaSisa];
        }

        $dasar = 0.0;
        $tambahan = 0.0;
        $namaMusim = [];
        foreach ($blok as [$waktu, $harga]) {
            $tanggal = $waktu->toDateString();
            $berlaku = $musimAktif->filter(fn (TarifMusim $m) => $m->mulai->toDateString() <= $tanggal && $m->selesai->toDateString() >= $tanggal);
            $persen = (float) ($berlaku->max('kenaikan_persen') ?? 0);
            if ($persen > 0) {
                $namaMusim[] = $berlaku->sortByDesc('kenaikan_persen')->first()->nama;
            }
            $dasar += $harga;
            $tambahan += round($harga * $persen / 100);
        }

        return [
            'durasi_jam' => $durasi,
            'jam_ditagih' => $jam,
            'hari' => $hari,
            'sisa_jam' => $sisa,
            'harga_hari' => (float) $tarif->harga_harian,
            'harga_sisa' => $hargaSisa,
            'harga_dasar' => round($dasar),
            'tambahan_musim' => $tambahan,
            'total' => round($dasar) + $tambahan,
            'musim' => array_values(array_unique($namaMusim)),
            'termasuk_bbm' => (bool) $tarif->termasuk_bbm,
        ];
    }
}
