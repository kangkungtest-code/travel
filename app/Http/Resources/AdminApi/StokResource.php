<?php

namespace App\Http\Resources\AdminApi;

use App\Models\Product;
use App\Support\NotifikasiAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Satu varian + stoknya (semua lokasi dijumlah; MVP hanya lokasi default). @mixin \App\Models\ProductVariant */
class StokResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fisik = (int) $this->stocks->sum('jumlah');
        $dipesan = (int) $this->stocks->sum('jumlah_reserved');
        $tersedia = $fisik - $dipesan;

        return [
            'varian_id' => $this->id,
            'sku' => $this->sku,
            'produk' => [
                'id' => $this->product?->id,
                'nama' => $this->product?->getTranslation('nama_terjemahan', 'id'),
                'aktif' => (bool) $this->product?->is_active,
            ],
            'opsi' => (object) ($this->opsi ?? []),
            'foto' => $this->product?->fotoUntuk($this->opsi[Product::OPSI_WARNA] ?? null)?->thumbUrl(),
            'harga_idr' => Format::rupiah($this->harga_idr),
            'stok' => [
                'fisik' => $fisik,
                'dipesan' => $dipesan,
                'tersedia' => $tersedia,
                'status' => $tersedia <= 0 ? 'habis' : ($tersedia <= NotifikasiAdmin::BATAS_MENIPIS ? 'menipis' : 'aman'),
            ],
        ];
    }
}
