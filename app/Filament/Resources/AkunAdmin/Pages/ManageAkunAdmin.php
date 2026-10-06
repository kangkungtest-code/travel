<?php

namespace App\Filament\Resources\AkunAdmin\Pages;

use App\Filament\Resources\AkunAdmin\AkunAdminResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAkunAdmin extends ManageRecords
{
    protected static string $resource = AkunAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => AkunAdminResource::simpan($data)),
        ];
    }
}
