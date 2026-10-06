<?php

namespace App\Support;

use App\Models\Order;
use App\Models\UnitKendaraan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cek unit kendaraan yang bebas di rentang waktu tertentu.
 * Unit "terpakai" kalau punya booking aktif yang bertumpang tindih, termasuk
 * jeda bersih-bersih sebelum & sesudahnya (config travel.booking.jeda_jam).
 */
class Ketersediaan
{
    /** Status order yang memegang unit. */
    public const STATUS_AKTIF = [
        Order::STATUS_MENUNGGU_PEMBAYARAN,
        Order::STATUS_DIBAYAR,
        Order::STATUS_DIPROSES,
        Order::STATUS_DIKIRIM,
    ];

    /** Batasi query unit (atau relasi unit) ke yang siap & tidak bentrok. */
    public static function filterBebas(Builder $unit, CarbonInterface $mulai, CarbonInterface $selesai, ?string $lokasiId = null, ?string $kecualiOrderId = null): Builder
    {
        $jeda = (int) config('travel.booking.jeda_jam', 0);
        $batasMulai = $mulai->copy()->utc()->subHours($jeda);
        $batasSelesai = $selesai->copy()->utc()->addHours($jeda);

        return $unit
            ->where('status', UnitKendaraan::SIAP)
            ->when($lokasiId, fn (Builder $q) => $q->where('lokasi_id', $lokasiId))
            ->whereDoesntHave('booking', fn (Builder $b) => $b
                ->aktif()
                ->when($kecualiOrderId, fn (Builder $q) => $q->where('order_id', '!=', $kecualiOrderId))
                ->where('mulai', '<', $batasSelesai)
                ->where('selesai', '>', $batasMulai));
    }

    public static function unitBebas(string $tipeId, CarbonInterface $mulai, CarbonInterface $selesai, ?string $lokasiId = null): Builder
    {
        return self::filterBebas(UnitKendaraan::query()->where('tipe_kendaraan_id', $tipeId), $mulai, $selesai, $lokasiId);
    }
}
