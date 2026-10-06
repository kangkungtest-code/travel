<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Stok\UbahStokAction;
use App\Exceptions\TokoException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminApi\Format;
use App\Http\Resources\AdminApi\StokResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockHistory;
use App\Support\NotifikasiAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StokController extends Controller
{
    private const TERSEDIA = '(select coalesce(sum(jumlah - jumlah_reserved), 0) from stocks where stocks.variant_id = product_variants.id)';

    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'in:menipis,habis'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $varian = ProductVariant::query()
            ->with(['stocks', 'product.images'])
            // Hasil scan barcode: SKU persis (tidak peka huruf besar/kecil).
            ->when($f['sku'] ?? null, fn (Builder $q, string $sku) => $q->whereRaw('LOWER(sku) = ?', [mb_strtolower(trim($sku))]))
            ->when($f['q'] ?? null, function (Builder $q, string $kata) {
                $like = '%'.addcslashes(mb_strtolower($kata), '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(sku) LIKE ?', [$like])
                    ->orWhereHas('product', fn (Builder $p) => Product::cariNama($p, $kata)));
            })
            ->when(($f['filter'] ?? null) === 'menipis', fn (Builder $q) => $q->whereRaw(self::TERSEDIA.' between 1 and ?', [NotifikasiAdmin::BATAS_MENIPIS]))
            ->when(($f['filter'] ?? null) === 'habis', fn (Builder $q) => $q->whereRaw(self::TERSEDIA.' <= 0'))
            ->orderByRaw(self::TERSEDIA.' asc')
            ->orderBy('sku')
            ->paginate($f['per_halaman'] ?? 30)
            ->withQueryString();

        return StokResource::collection($varian);
    }

    public function show(ProductVariant $varian): StokResource
    {
        return new StokResource($varian->load(['stocks', 'product.images']));
    }

    /**
     * jenis=restock: tambah `jumlah` barang masuk.
     * jenis=koreksi: setel stok fisik menjadi `jumlah` (hasil hitung ulang gudang).
     */
    public function ubah(Request $request, ProductVariant $varian, UbahStokAction $ubah): StokResource
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:restock,koreksi'],
            'jumlah' => ['required', 'integer', $request->input('jenis') === 'restock' ? 'min:1' : 'min:0', 'max:100000'],
        ]);

        $fisik = (int) $varian->stocks()->sum('jumlah');
        $perubahan = $data['jenis'] === 'restock' ? (int) $data['jumlah'] : (int) $data['jumlah'] - $fisik;

        if ($perubahan === 0) {
            throw new TokoException("Stok fisik sudah {$fisik}, tidak ada yang diubah.");
        }

        $ubah->execute($varian, $perubahan, $data['jenis'] === 'restock' ? UbahStokAction::ALASAN_RESTOCK : UbahStokAction::ALASAN_KOREKSI);

        return new StokResource($varian->fresh(['stocks', 'product.images']));
    }

    public function riwayat(ProductVariant $varian): JsonResponse
    {
        $riwayat = StockHistory::query()
            ->where('variant_id', $varian->id)
            ->with('order:id,nomor')
            ->latest()
            ->orderByDesc('id') // UUID berurutan waktu: penentu urutan kalau dicatat di detik yang sama
            ->paginate(30);

        return response()->json([
            'data' => $riwayat->getCollection()->map(fn (StockHistory $h) => [
                'perubahan' => (int) $h->perubahan,
                'alasan' => $h->alasan,
                'label' => [
                    'restock' => 'Barang masuk', 'koreksi' => 'Koreksi stok', 'reserve' => 'Dipesan',
                    'lepas' => 'Pesanan batal/kadaluarsa', 'kurangi' => 'Terjual',
                ][$h->alasan] ?? $h->alasan,
                'pesanan' => $h->order?->nomor,
                'waktu' => Format::waktu($h->created_at),
            ]),
            'meta' => ['halaman' => $riwayat->currentPage(), 'halaman_terakhir' => $riwayat->lastPage(), 'total' => $riwayat->total()],
        ]);
    }
}
