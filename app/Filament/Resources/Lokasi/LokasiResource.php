<?php

namespace App\Filament\Resources\Lokasi;

use App\Filament\Resources\Lokasi\Pages\ManageLokasi;
use App\Models\Lokasi;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LokasiResource extends Resource
{
    protected static ?string $model = Lokasi::class;

    protected static ?string $slug = 'lokasi';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string | UnitEnum | null $navigationGroup = 'Armada';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'lokasi';

    protected static ?string $pluralModelLabel = 'Lokasi pool';

    protected static ?string $navigationLabel = 'Lokasi pool';

    /** Isian lokasi; dipakai juga oleh tombol "lokasi baru" di form unit. */
    public static function isian(): array
    {
        return [
            Tabs::make('bahasa')
                ->columnSpanFull()
                ->tabs(collect(config('toko.locales'))
                    ->map(fn (string $label, string $locale) => Tab::make($label)
                        ->key('lokasi-'.$locale)
                        ->schema([
                            TextInput::make("nama_terjemahan.{$locale}")
                                ->label('Nama lokasi')
                                ->placeholder($locale === 'id' ? 'mis. Pool Denpasar' : null)
                                ->required(in_array($locale, config('toko.required_locales'), true))
                                ->maxLength(100),
                        ]))
                    ->values()->all()),
            TextInput::make('kota')->label('Kota')->required()->maxLength(100),
            TextInput::make('jam_operasional')->label('Jam operasional')->placeholder('mis. 07.00–21.00')->maxLength(100),
            Textarea::make('alamat')->label('Alamat')->rows(2)->columnSpanFull(),
            TextInput::make('url_peta')->label('Tautan Google Maps')->url()->maxLength(500)->columnSpanFull(),
            Toggle::make('is_active')->label('Aktif')->helperText('Lokasi nonaktif tidak bisa dipilih pembeli.')->default(true)->inline(false),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::isian());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->reorderable('urutan')
            ->paginated(false)
            ->columns([
                TextColumn::make('nama')->label('Lokasi')
                    ->state(fn (Lokasi $l) => $l->nama('id'))
                    ->description(fn (Lokasi $l) => $l->kota),
                TextColumn::make('jam_operasional')->label('Jam')->placeholder('—'),
                TextColumn::make('unit_count')->label('Unit')->counts('unit'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Lokasi $record) {
                        $data['nama_terjemahan'] = $record->getTranslations('nama_terjemahan');

                        return $data;
                    }),
                DeleteAction::make()
                    ->before(function (Lokasi $record, DeleteAction $action) {
                        if ($record->unit()->exists()) {
                            Notification::make()->danger()
                                ->title('Lokasi masih dipakai unit')
                                ->body('Pindahkan unitnya ke lokasi lain dulu, atau nonaktifkan lokasi ini.')
                                ->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLokasi::route('/')];
    }
}
