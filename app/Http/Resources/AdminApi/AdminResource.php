<?php

namespace App\Http\Resources\AdminApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class AdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama_lengkap,
            'email' => $this->email,
            'peran' => $this->getRoleNames()->values(),
            'izin' => $this->getAllPermissions()->pluck('name')->sort()->values(),
        ];
    }
}
