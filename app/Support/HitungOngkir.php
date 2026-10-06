<?php

namespace App\Support;

use App\Models\ShippingRate;
use App\Models\ShippingZone;

/** Ongkir flat per zona negara dan rentang berat (dalam IDR). */
class HitungOngkir
{
    /** @return float|null null kalau negara/berat belum ada tarifnya */
    public function hitung(string $negara, int $beratGram): ?float
    {
        $zona = ShippingZone::untukNegara($negara);
        if (! $zona) {
            return null;
        }

        $berat = max(1, $beratGram);

        /** @var ShippingRate|null $tarif */
        $tarif = $zona->rates()
            ->where('berat_min_gram', '<=', $berat)
            ->where('berat_max_gram', '>=', $berat)
            ->first();

        return $tarif ? (float) $tarif->tarif_idr : null;
    }
}
