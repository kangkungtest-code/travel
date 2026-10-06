<?php

namespace App\Filament\Resources\ExchangeRates;

use App\Filament\Resources\ExchangeRates\Pages\ManageExchangeRates;
use App\Models\ExchangeRate;
use App\Support\Kurs;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Kurs manual IDR -> mata uang tampilan. Kurs lama tidak diubah, cukup tambah kurs
 * baru dengan "berlaku dari" — order lama tetap memakai kurs snapshot-nya.
 */
class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string | UnitEnum | null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'kurs';

    protected static ?string $pluralModelLabel = 'kurs';

    protected static ?string $navigationLabel = 'Kurs';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('mata_uang_tujuan')->label('Mata uang')
                    ->options(collect(config('toko.currencies'))->reject(fn ($c) => $c === config('toko.base_currency'))->mapWithKeys(fn ($c) => [$c => $c]))
                    ->required()->live(),
                DateTimePicker::make('berlaku_dari')->label('Berlaku dari')->default(now())->required()->seconds(false),
                TextInput::make('per_unit')->label(fn (Get $get) => '1 '.($get('mata_uang_tujuan') ?: 'USD').' = berapa rupiah?')
                    ->prefix('Rp')->numeric()->minValue(1)->required()
                    ->helperText('Mis. 16500 untuk USD. Sistem menyimpannya sebagai rate 1 IDR = 1/16500.'),
                TextInput::make('margin_persen')->label('Margin')->suffix('%')->numeric()->minValue(0)->maxValue(20)->default(2)->required()
                    ->helperText('Ditambahkan ke harga dalam mata uang asing untuk menutup selisih kurs.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('berlaku_dari', 'desc')
            ->columns([
                TextColumn::make('mata_uang_tujuan')->label('Mata uang')->badge(),
                TextColumn::make('per_unit')->label('1 unit =')
                    ->state(fn (ExchangeRate $r) => 'Rp'.number_format(1 / max((float) $r->rate, 1e-12), 2, ',', '.')),
                TextColumn::make('margin_persen')->label('Margin')->suffix('%'),
                TextColumn::make('berlaku_dari')->label('Berlaku dari')->dateTime('d M Y H:i', config('toko.zona_waktu'))->sortable(),
                TextColumn::make('aktif')->label('')
                    ->state(fn (ExchangeRate $r) => self::sedangDipakai($r) ? 'Sedang dipakai' : null)
                    ->badge()->color('success'),
                TextColumn::make('sumber')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('mata_uang_tujuan')->label('Mata uang')
                    ->options(collect(config('toko.currencies'))->mapWithKeys(fn ($c) => [$c => $c])),
            ])
            ->recordActions([DeleteAction::make()]);
    }

    public static function sedangDipakai(ExchangeRate $r): bool
    {
        return ExchangeRate::query()
            ->where('mata_uang_asal', $r->mata_uang_asal)
            ->where('mata_uang_tujuan', $r->mata_uang_tujuan)
            ->where('berlaku_dari', '<=', now())
            ->latest('berlaku_dari')
            ->value('id') === $r->id;
    }

    public static function getPages(): array
    {
        return ['index' => ManageExchangeRates::route('/')];
    }
}
