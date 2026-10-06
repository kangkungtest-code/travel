<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Stocks\StockResource;
use App\Models\Stock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StokMenipis extends TableWidget
{
    protected static ?int $sort = 7;

    protected static ?string $heading = 'Stok menipis';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Stock::query()
                ->with('variant.product')
                ->select('stocks.*')
                ->selectRaw('(jumlah - jumlah_reserved) as tersedia')
                ->whereRaw('(jumlah - jumlah_reserved) <= ?', [StockResource::BATAS_MENIPIS])
                ->orderBy('tersedia'))
            ->paginated([5])
            ->emptyStateHeading('Semua stok aman')
            ->columns([
                TextColumn::make('produk')->label('Produk')
                    ->state(fn (Stock $s) => $s->variant?->product?->getTranslation('nama_terjemahan', 'id'))
                    ->description(fn (Stock $s) => $s->variant?->sku),
                TextColumn::make('tersedia')->label('Tersedia')->badge()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'warning'),
            ])
            ->headerActions([])
            ->recordUrl(fn () => StockResource::getUrl());
    }
}
