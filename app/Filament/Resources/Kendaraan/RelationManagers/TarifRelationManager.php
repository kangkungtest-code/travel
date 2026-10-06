<?php

namespace App\Filament\Resources\Kendaraan\RelationManagers;

use App\Models\Tarif;
use App\Support\Spesifikasi;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class TarifRelationManager extends RelationManager
{
    protected static string $relationship = 'tarif';

    protected static ?string $title = 'Tarif';

    protected static ?string $modelLabel = 'tarif';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('mode')
                    ->label('Mode sewa')
                    ->options(Spesifikasi::pilihan('mode'))
                    ->required()
                    ->native(false)
                    ->live()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('tipe_kendaraan_id', $this->getOwnerRecord()->getKey()))
                    ->validationMessages(['unique' => 'Tarif untuk mode ini sudah ada. Ubah tarif yang ada.']),
                TextInput::make('minimal_jam')
                    ->label('Minimal sewa')
                    ->suffix('jam')
                    ->integer()->minValue(1)->maxValue(720)
                    ->default(12)
                    ->required(),
                TextInput::make('harga_harian')
                    ->label('Harga per 24 jam')
                    ->prefix('Rp')->numeric()->minValue(0)->required(),
                TextInput::make('harga_12jam')
                    ->label('Harga 12 jam')
                    ->helperText('Opsional. Dipakai untuk sisa waktu ≤ 12 jam.')
                    ->prefix('Rp')->numeric()->minValue(0),
                TextInput::make('harga_per_jam')
                    ->label('Harga per jam (tambahan)')
                    ->helperText('Opsional. Untuk sisa jam / keterlambatan; tidak pernah melebihi harga 12 jam/harian.')
                    ->prefix('Rp')->numeric()->minValue(0),
                Toggle::make('termasuk_bbm')
                    ->label('Termasuk BBM')
                    ->visible(fn (Get $get) => $get('mode') === Tarif::SOPIR)
                    ->inline(false),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        $rp = fn ($state) => $state === null ? null : 'Rp'.number_format((float) $state, 0, ',', '.');

        return $table
            ->recordTitle(fn (Tarif $record) => Spesifikasi::label('mode', $record->mode, 'id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('mode')->label('Mode')
                    ->formatStateUsing(fn (string $state) => Spesifikasi::label('mode', $state, 'id'))
                    ->badge(),
                TextColumn::make('harga_harian')->label('24 jam')->formatStateUsing($rp),
                TextColumn::make('harga_12jam')->label('12 jam')->formatStateUsing($rp)->placeholder('—'),
                TextColumn::make('harga_per_jam')->label('Per jam')->formatStateUsing($rp)->placeholder('—'),
                TextColumn::make('minimal_jam')->label('Minimal')->suffix(' jam'),
                IconColumn::make('termasuk_bbm')->label('BBM')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->visible(fn () => $this->getOwnerRecord()->tarif()->count() < count(config('travel.mode'))),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
