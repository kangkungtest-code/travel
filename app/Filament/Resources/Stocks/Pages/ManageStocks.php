<?php

namespace App\Filament\Resources\Stocks\Pages;

use App\Filament\Resources\Stocks\StockResource;
use Filament\Resources\Pages\ManageRecords;

class ManageStocks extends ManageRecords
{
    protected static string $resource = StockResource::class;
}
