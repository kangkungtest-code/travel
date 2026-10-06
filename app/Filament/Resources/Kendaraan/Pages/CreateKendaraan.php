<?php

namespace App\Filament\Resources\Kendaraan\Pages;

use App\Filament\Resources\Kendaraan\KendaraanResource;
use App\Models\TipeKendaraan;
use Filament\Resources\Pages\CreateRecord;

class CreateKendaraan extends CreateRecord
{
    protected static string $resource = KendaraanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['urutan'] = (int) TipeKendaraan::max('urutan') + 1;

        return $data;
    }

    /** Setelah dibuat, langsung ke halaman edit untuk menambah unit (plat nomor). */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
