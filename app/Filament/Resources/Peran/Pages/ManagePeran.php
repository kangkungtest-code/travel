<?php

namespace App\Filament\Resources\Peran\Pages;

use App\Filament\Resources\Peran\PeranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePeran extends ManageRecords
{
    protected static string $resource = PeranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => PeranResource::simpan($data)),
        ];
    }
}
