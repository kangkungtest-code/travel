<?php

namespace App\Http\Resources\AdminApi;

use App\Filament\Support\LabelAdmin;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReturnRequest */
class ReturResource extends JsonResource
{
    public const AKSI = [
        ReturnRequest::STATUS_DIAJUKAN => [
            ['kode' => 'setujui', 'label' => 'Setujui', 'wajib' => []],
            ['kode' => 'tolak', 'label' => 'Tolak', 'wajib' => ['catatan']],
        ],
        ReturnRequest::STATUS_DISETUJUI => [
            ['kode' => 'selesaikan', 'label' => 'Barang diterima, selesaikan', 'wajib' => ['penyelesaian']],
        ],
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'label_status' => LabelAdmin::STATUS_RETUR[$this->status] ?? $this->status,
            'alasan' => $this->alasan,
            'foto_url' => $this->fotoUrl(),
            'resi_kembali' => $this->resi_kembali,
            'catatan_admin' => $this->catatan_admin,
            'penyelesaian' => $this->penyelesaian,
            'pesanan' => [
                'nomor' => $this->order?->nomor,
                'pembeli' => $this->order?->alamat_snapshot['nama_penerima'] ?? $this->order?->user?->nama_lengkap,
                'total_idr' => Format::rupiah($this->order?->total_idr),
            ],
            'dibuat_pada' => Format::waktu($this->created_at),
            'diperbarui_pada' => Format::waktu($this->updated_at),
            'aksi' => $request->user()?->hasPermissionTo('retur.kelola') ? (self::AKSI[$this->status] ?? []) : [],
        ];
    }
}
