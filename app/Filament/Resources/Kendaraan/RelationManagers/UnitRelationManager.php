<?php

namespace App\Filament\Resources\Kendaraan\RelationManagers;

use App\Filament\Resources\Lokasi\LokasiResource;
use App\Models\Lokasi;
use App\Models\UnitKendaraan;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnitRelationManager extends RelationManager
{
    protected static string $relationship = 'unit';

    protected static ?string $title = 'Unit (plat nomor)';

    protected static ?string $modelLabel = 'unit';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('plat_nomor')
                    ->label('Plat nomor')
                    ->placeholder('DK 1234 AB')
                    ->required()
                    ->maxLength(20)
                    ->dehydrateStateUsing(fn (?string $state) => UnitKendaraan::rapikanPlat((string) $state))
                    ->rule(fn (?UnitKendaraan $record) => function (string $attr, $value, \Closure $fail) use ($record) {
                        $plat = UnitKendaraan::rapikanPlat((string) $value);
                        if (UnitKendaraan::where('plat_nomor', $plat)->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists()) {
                            $fail('Plat nomor ini sudah terdaftar.');
                        }
                    }),
                Select::make('lokasi_id')
                    ->label('Lokasi pool')
                    ->relationship('lokasi', 'kota', fn ($q) => $q->orderBy('urutan'))
                    ->getOptionLabelFromRecordUsing(fn (Lokasi $l) => $l->nama('id').' — '.$l->kota)
                    ->default(fn () => Lokasi::aktif()->value('id'))
                    ->required()
                    ->preload()
                    ->createOptionForm(LokasiResource::isian())
                    ->createOptionUsing(fn (array $data) => Lokasi::create($data + ['urutan' => (int) Lokasi::max('urutan') + 1])->getKey())
                    ->createOptionModalHeading('Lokasi baru'),
                TextInput::make('tahun')->label('Tahun')->integer()->minValue(1980)->maxValue((int) date('Y') + 1),
                TextInput::make('warna')->label('Warna')->maxLength(50),
                Select::make('status')
                    ->label('Status')
                    ->options(config('travel.status_unit'))
                    ->default(UnitKendaraan::SIAP)
                    ->required()
                    ->native(false),
                Textarea::make('catatan')->label('Catatan internal')->rows(2)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('plat_nomor')
            ->modifyQueryUsing(fn ($q) => $q->with('lokasi'))
            ->defaultSort('plat_nomor')
            ->columns([
                TextColumn::make('plat_nomor')->label('Plat')->weight('bold')->searchable(),
                TextColumn::make('lokasi')->label('Lokasi')->state(fn (UnitKendaraan $u) => $u->lokasi?->nama('id')),
                TextColumn::make('tahun')->label('Tahun')->placeholder('—'),
                TextColumn::make('warna')->label('Warna')->placeholder('—'),
                TextColumn::make('status')->label('Status')
                    ->formatStateUsing(fn (string $state) => config("travel.status_unit.{$state}", $state))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        UnitKendaraan::SIAP => 'success',
                        UnitKendaraan::PERAWATAN => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(config('travel.status_unit')),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Unit dihapus permanen. Kalau hanya tidak dipakai sementara, ubah statusnya ke "Nonaktif".'),
            ]);
    }
}
