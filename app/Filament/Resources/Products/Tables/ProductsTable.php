<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['images', 'variants.stocks', 'category']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('foto')
                    ->label('')
                    ->state(fn (Product $record): ?string => $record->images->first()?->thumbUrl())
                    ->imageHeight(48),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->state(fn (Product $record): string => $record->getTranslation('nama_terjemahan', 'id'))
                    ->description(fn (Product $record): string => $record->getTranslation('nama_terjemahan', 'en'))
                    ->searchable(query: fn (Builder $query, string $search) => Product::cariNama($query, $search)),
                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->state(fn (Product $record): ?string => $record->category?->nama('id'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('variants_count')
                    ->label('Varian')
                    ->counts('variants')
                    ->sortable(),
                TextColumn::make('stok')
                    ->label('Stok tersedia')
                    ->state(fn (Product $record): int => $record->variants->sum(fn ($v) => $v->stokTersedia())),
                IconColumn::make('is_active')
                    ->label('Tampil')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->options(fn () => \App\Models\Category::query()->orderBy('urutan')->get()->mapWithKeys(fn ($c) => [$c->id => $c->nama('id')])->all()),
                TernaryFilter::make('is_active')->label('Tampil di toko'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
