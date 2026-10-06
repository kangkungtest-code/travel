<?php

namespace App\Http\Resources\AdminApi;

use App\Filament\Support\LabelAdmin;
use App\Payments\MetodePembayaran;
use App\Support\Kurs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class PesananRingkasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lunas = $this->relationLoaded('payments')
            ? $this->payments->firstWhere('status', \App\Models\Payment::BERHASIL)
            : null;

        return [
            'nomor' => $this->nomor,
            'status' => $this->status,
            'label_status' => LabelAdmin::STATUS_ORDER[$this->status] ?? $this->status,
            'pembeli' => [
                'nama' => $this->alamat_snapshot['nama_penerima'] ?? $this->user?->nama_lengkap,
                'email' => $this->user?->email,
            ],
            'jumlah_barang' => (int) ($this->items_sum_qty ?? $this->items?->sum('qty') ?? 0),
            'total_idr' => Format::rupiah($this->total_idr),
            // Total dalam mata uang yang dilihat pembeli saat checkout.
            'total_pembeli' => [
                'mata_uang' => $this->mata_uang,
                'nilai' => (float) $this->total,
                'teks' => app(Kurs::class)->formatNilai((float) $this->total, $this->mata_uang),
            ],
            'negara' => $this->alamat_snapshot['negara'] ?? null,
            'metode_bayar' => $lunas ? MetodePembayaran::label($lunas->gateway) : null,
            'resi' => $this->resi,
            'dibuat_pada' => Format::waktu($this->created_at),
            'dibayar_pada' => Format::waktu($this->dibayar_pada),
            'kadaluarsa_pada' => Format::waktu($this->kadaluarsa_pada),
        ];
    }
}
