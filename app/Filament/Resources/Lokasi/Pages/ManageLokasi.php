<?php

namespace App\Filament\Resources\Lokasi\Pages;

use App\Filament\Resources\Lokasi\LokasiResource;
use App\Models\Lokasi;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLokasi extends ManageRecords
{
    protected static string $resource = LokasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(function (array $data) {
                $data['urutan'] = (int) Lokasi::max('urutan') + 1;

                return $data;
            }),
        ];
    }
}
