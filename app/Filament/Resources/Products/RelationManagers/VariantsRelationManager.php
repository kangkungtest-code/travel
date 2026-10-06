<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Katalog\BuatVarianAction;
use App\Actions\Stok\UbahStokAction;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Varian & stok';

    protected static ?string $modelLabel = 'varian';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('harga_idr')
                    ->label('Harga')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                KeyValue::make('opsi')
                    ->label('Opsi')
                    ->keyLabel('Opsi (mis. Ukuran)')
                    ->valueLabel('Nilai (mis. M)')
                    ->addActionLabel('Tambah opsi')
                    ->columnSpanFull(),
                TextInput::make('berat_gram')
                    ->label('Berat')
                    ->suffix('gram')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                TextInput::make('stok_awal')
                    ->label('Stok awal')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->visibleOn(Operation::Create)
                    ->helperText('Perubahan stok berikutnya lewat tombol "Ubah stok".'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('stocks'))
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('opsi')
                    ->label('Opsi')
                    ->state(fn (ProductVariant $record): string => collect($record->opsi ?? [])
                        ->map(fn ($nilai, $opsi) => "{$opsi}: {$nilai}")
                        ->implode(', ') ?: '—'),
                TextColumn::make('harga_idr')->label('Harga')->money('IDR', locale: 'id')->sortable(),
                TextColumn::make('berat_gram')->label('Berat')->suffix(' g'),
                TextColumn::make('stok')
                    ->label('Stok tersedia')
                    ->state(fn (ProductVariant $record): int => $record->stokTersedia())
                    ->badge()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : ($state <= 5 ? 'warning' : 'success')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data, RelationManager $livewire): Model => app(BuatVarianAction::class)
                        ->execute($livewire->getOwnerRecord(), $data)),
            ])
            ->recordActions([
                Action::make('ubahStok')
                    ->label('Ubah stok')
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->schema([
                        Radio::make('alasan')
                            ->options([
                                UbahStokAction::ALASAN_RESTOCK => 'Restock (barang masuk)',
                                UbahStokAction::ALASAN_KOREKSI => 'Koreksi (hasil hitung ulang / rusak / hilang)',
                            ])
                            ->default(UbahStokAction::ALASAN_RESTOCK)
                            ->required(),
                        TextInput::make('perubahan')
                            ->label('Jumlah')
                            ->helperText('Angka negatif untuk mengurangi, mis. -2.')
                            ->integer()
                            ->required()
                            ->notIn([0]),
                    ])
                    ->action(function (ProductVariant $record, array $data, Action $action): void {
                        try {
                            app(UbahStokAction::class)->execute($record, (int) $data['perubahan'], $data['alasan']);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->danger()->title('Stok tidak diubah')->body($e->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title('Stok diperbarui')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
