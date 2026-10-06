<?php

namespace App\Filament\Resources\ShippingZones;

use App\Filament\Resources\ShippingZones\Pages\ManageShippingZones;
use App\Models\ShippingZone;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Ongkir flat: zona (kumpulan negara) + tarif per rentang berat. */
class ShippingZoneResource extends Resource
{
    /** Travel: menu toko barang disembunyikan (config travel.menu_toko_barang). */
    public static function shouldRegisterNavigation(): bool
    {
        return config('travel.menu_toko_barang') && parent::shouldRegisterNavigation();
    }

    protected static ?string $model = ShippingZone::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string | UnitEnum | null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'zona ongkir';

    protected static ?string $pluralModelLabel = 'zona ongkir';

    protected static ?string $navigationLabel = 'Ongkir';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nama')->label('Nama zona')->required()->maxLength(100),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                Select::make('negara')
                    ->label('Negara tujuan')
                    ->multiple()
                    ->options(config('toko.negara'))
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Pembeli hanya bisa memilih negara yang ada di zona aktif.'),
                Repeater::make('rates')
                    ->label('Tarif per berat')
                    ->relationship()
                    ->reorderable(false)
                    ->defaultItems(1)
                    ->columns(3)
                    ->columnSpanFull()
                    ->addActionLabel('Tambah rentang berat')
                    ->schema([
                        TextInput::make('berat_min_gram')->label('Dari (gram)')->integer()->minValue(0)->required(),
                        TextInput::make('berat_max_gram')->label('Sampai (gram)')->integer()->minValue(1)->required()->gte('berat_min_gram'),
                        TextInput::make('tarif_idr')->label('Tarif')->prefix('Rp')->numeric()->minValue(0)->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Zona'),
                TextColumn::make('negara')
                    ->label('Negara')
                    ->formatStateUsing(fn (string $state): string => config("toko.negara.{$state}", $state))
                    ->badge(),
                TextColumn::make('rates_count')->label('Rentang berat')->counts('rates'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShippingZones::route('/'),
        ];
    }
}
