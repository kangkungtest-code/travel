<?php

namespace App\Http\Resources\AdminApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Illuminate\Notifications\DatabaseNotification */
class NotifikasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jenis' => $this->data['jenis'] ?? $this->type,
            'judul' => $this->data['judul'] ?? '',
            'isi' => $this->data['isi'] ?? '',
            'tujuan' => $this->data['tujuan'] ?? null,
            'dibaca' => $this->read_at !== null,
            'dibuat_pada' => Format::waktu($this->created_at),
        ];
    }
}
