<?php

namespace App\Actions\Stok;

use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tambah / kurangi stok fisik secara manual (restock, koreksi).
 * Pakai row lock + transaksi, dan selalu mencatat riwayat di stock_histories.
 *
 * Reserve / lepas stok karena order akan punya Action sendiri (Fase 3).
 */
class UbahStokAction
{
    public const ALASAN_RESTOCK = 'restock';
    public const ALASAN_KOREKSI = 'koreksi';

    public function execute(
        ProductVariant $variant,
        int $perubahan,
        string $alasan = self::ALASAN_RESTOCK,
        ?StockLocation $lokasi = null,
    ): Stock {
        if ($perubahan === 0) {
            throw new InvalidArgumentException('Perubahan stok tidak boleh 0.');
        }

        $lokasi ??= StockLocation::default()
            ?? throw new InvalidArgumentException('Belum ada lokasi stok default.');

        return DB::transaction(function () use ($variant, $perubahan, $alasan, $lokasi) {
            $stock = Stock::query()
                ->where('variant_id', $variant->id)
                ->where('location_id', $lokasi->id)
                ->lockForUpdate()
                ->first()
                ?? Stock::create([
                    'variant_id' => $variant->id,
                    'location_id' => $lokasi->id,
                    'jumlah' => 0,
                    'jumlah_reserved' => 0,
                ]);

            $baru = $stock->jumlah + $perubahan;

            if ($baru < $stock->jumlah_reserved) {
                throw new InvalidArgumentException(
                    "Stok tidak bisa dikurangi di bawah jumlah yang sedang di-reserve order ({$stock->jumlah_reserved})."
                );
            }

            $stock->update(['jumlah' => $baru]);

            StockHistory::create([
                'variant_id' => $variant->id,
                'perubahan' => $perubahan,
                'alasan' => $alasan,
            ]);

            return $stock;
        });
    }
}
