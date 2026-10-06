<?php

namespace App\Filament\Resources\TarifMusim;

use App\Filament\Resources\TarifMusim\Pages\ManageTarifMusim;
use App\Models\TarifMusim;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TarifMusimResource extends Resource
{
    protected static ?string $model = TarifMusim::class;

    protected static ?string $slug = 'tarif-musim';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSun;

    protected static string | UnitEnum | null $navigationGroup = 'Armada';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'musim ramai';

    protected static ?string $pluralModelLabel = 'Musim ramai';

    protected static ?string $navigationLabel = 'Musim ramai';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nama')->label('Nama')->placeholder('mis. Libur Natal & Tahun Baru')->required()->maxLength(100)->columnSpanFull(),
            DatePicker::make('mulai')->label('Mulai')->required()->native(false)->displayFormat('d M Y'),
            DatePicker::make('selesai')->label('Selesai')->required()->native(false)->displayFormat('d M Y')
                ->afterOrEqual('mulai')
                ->validationMessages(['after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']),
            TextInput::make('kenaikan_persen')->label('Kenaikan harga')->suffix('%')->numeric()->minValue(1)->maxValue(300)->required()
                ->helperText('Berlaku untuk semua kendaraan & mode, dihitung per hari sewa yang jatuh di rentang ini.'),
            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('mulai', 'desc')
            ->columns([
                TextColumn::make('nama')->label('Nama')->weight('bold'),
                TextColumn::make('rentang')->label('Tanggal')
                    ->state(fn (TarifMusim $record) => $record->mulai->translatedFormat('d M Y').' – '.$record->selesai->translatedFormat('d M Y')),
                TextColumn::make('kenaikan_persen')->label('Kenaikan')->formatStateUsing(fn ($state) => '+'.rtrim(rtrim(number_format((float) $state, 2, ',', ''), '0'), ',').'%'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTarifMusim::route('/')];
    }
}
