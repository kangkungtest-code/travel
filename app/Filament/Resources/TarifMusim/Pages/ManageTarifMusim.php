<?php

namespace App\Filament\Resources\TarifMusim\Pages;

use App\Filament\Resources\TarifMusim\TarifMusimResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTarifMusim extends ManageRecords
{
    protected static string $resource = TarifMusimResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
