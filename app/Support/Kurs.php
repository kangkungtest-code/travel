<?php

namespace App\Support;

use App\Models\ExchangeRate;

/**
 * Konversi harga dari IDR (base currency) ke mata uang tampilan.
 * Kurs efektif = rate x (1 + margin%). Kalau kurs belum ada, harga tetap IDR.
 */
class Kurs
{
    /** @var array<string, float|null> */
    private array $cache = [];

    public function rateEfektif(string $tujuan): ?float
    {
        $tujuan = strtoupper($tujuan);
        $base = config('toko.base_currency');

        if ($tujuan === $base) {
            return 1.0;
        }

        if (! array_key_exists($tujuan, $this->cache)) {
            $kurs = ExchangeRate::query()
                ->where('mata_uang_asal', $base)
                ->where('mata_uang_tujuan', $tujuan)
                ->where('berlaku_dari', '<=', now())
                ->latest('berlaku_dari')
                ->first();

            $this->cache[$tujuan] = $kurs
                ? (float) $kurs->rate * (1 + (float) $kurs->margin_persen / 100)
                : null;
        }

        return $this->cache[$tujuan];
    }

    /** Mata uang yang benar-benar dipakai (jatuh ke IDR kalau kurs belum diisi admin). */
    public function mataUangEfektif(string $diminta): string
    {
        return $this->rateEfektif($diminta) === null ? config('toko.base_currency') : strtoupper($diminta);
    }

    public function konversi(float $idr, string $tujuan): float
    {
        $tujuan = $this->mataUangEfektif($tujuan);

        return round($idr * $this->rateEfektif($tujuan), $this->desimal($tujuan));
    }

    /** Konversi dengan kurs snapshot (mis. harga item di order lama). */
    public function konversiDenganRate(float $idr, float $rate, string $tujuan): float
    {
        return round($idr * $rate, $this->desimal($tujuan));
    }

    public function format(float $idr, string $tujuan): string
    {
        $tujuan = $this->mataUangEfektif($tujuan);

        return $this->formatNilai($this->konversi($idr, $tujuan), $tujuan);
    }

    /** Format nilai yang sudah dalam mata uang tujuan (mis. total order). */
    public function formatNilai(float $nilai, string $tujuan): string
    {
        $tujuan = strtoupper($tujuan);

        return match ($tujuan) {
            'IDR' => 'Rp'.number_format($nilai, 0, ',', '.'),
            'USD' => '$'.number_format($nilai, 2, '.', ','),
            'TWD' => 'NT$'.number_format($nilai, 0, '.', ','),
            default => $tujuan.' '.number_format($nilai, 2, '.', ','),
        };
    }

    public function desimal(string $mataUang): int
    {
        return in_array(strtoupper($mataUang), ['IDR', 'TWD'], true) ? 0 : 2;
    }
}
