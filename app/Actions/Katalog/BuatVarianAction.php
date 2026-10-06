<?php

namespace App\Actions\Katalog;

use App\Actions\Stok\UbahStokAction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

/**
 * Buat varian baru + baris stok di lokasi default, opsional dengan stok awal.
 */
class BuatVarianAction
{
    public function __construct(private UbahStokAction $ubahStok) {}

    /**
     * @param  array{sku: string, opsi?: array<string, string>|null, harga_idr: numeric, berat_gram?: int|null, stok_awal?: int|null}  $data
     */
    public function execute(Product $product, array $data): ProductVariant
    {
        $stokAwal = (int) ($data['stok_awal'] ?? 0);
        unset($data['stok_awal']);

        return DB::transaction(function () use ($product, $data, $stokAwal) {
            $variant = $product->variants()->create($data);

            if ($stokAwal !== 0) {
                $this->ubahStok->execute($variant, $stokAwal, UbahStokAction::ALASAN_RESTOCK);
            } elseif ($lokasi = StockLocation::default()) {
                Stock::create([
                    'variant_id' => $variant->id,
                    'location_id' => $lokasi->id,
                    'jumlah' => 0,
                    'jumlah_reserved' => 0,
                ]);
            }

            return $variant;
        });
    }
}
