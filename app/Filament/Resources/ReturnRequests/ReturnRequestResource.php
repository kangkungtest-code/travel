<?php

namespace App\Filament\Resources\ReturnRequests;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\ReturnRequests\Pages\ViewReturnRequest;
use App\Filament\Support\LabelAdmin;
use App\Models\ReturnRequest;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReturnRequestResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string | UnitEnum | null $navigationGroup = 'Penjualan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'retur';

    protected static ?string $pluralModelLabel = 'retur';

    protected static ?string $navigationLabel = 'Retur';

    public static function canAccess(): bool
    {
        return \App\Support\Fitur::terlihatAdmin('retur') && parent::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ReturnRequest::query()->where('status', ReturnRequest::STATUS_DIAJUKAN)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.nomor')->label('Order')->searchable()->weight('bold'),
                TextColumn::make('order.user.nama_lengkap')->label('Pembeli'),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i', config('toko.zona_waktu'))->sortable(),
                TextColumn::make('alasan')->limit(60),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => LabelAdmin::STATUS_RETUR[$state] ?? $state)
                    ->color(fn (string $state) => LabelAdmin::WARNA_RETUR[$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(LabelAdmin::STATUS_RETUR),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Pengajuan')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('order.nomor')->label('Order')
                            ->url(fn (ReturnRequest $r) => OrderResource::getUrl('view', ['record' => $r->order])),
                        TextEntry::make('status')->badge()
                            ->formatStateUsing(fn (string $state) => LabelAdmin::STATUS_RETUR[$state] ?? $state)
                            ->color(fn (string $state) => LabelAdmin::WARNA_RETUR[$state] ?? 'gray'),
                        TextEntry::make('order.user.nama_lengkap')->label('Pembeli')
                            ->helperText(fn (ReturnRequest $r) => $r->order->user?->email),
                        TextEntry::make('created_at')->label('Diajukan')->dateTime('d M Y H:i', config('toko.zona_waktu')),
                        TextEntry::make('alasan')->columnSpanFull(),
                        TextEntry::make('resi_kembali')->label('Resi pengiriman balik')->placeholder('Belum diisi pembeli')->copyable(),
                        TextEntry::make('penyelesaian')->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => ['refund' => 'Refund', 'ganti_barang' => 'Ganti barang'][$state] ?? $state),
                        TextEntry::make('catatan_admin')->label('Catatan untuk pembeli')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Foto bukti')
                    ->columnSpan(1)
                    ->schema([
                        ImageEntry::make('foto_bukti')->hiddenLabel()->disk('public')->imageHeight(280)
                            ->url(fn (ReturnRequest $r) => $r->fotoUrl(), shouldOpenInNewTab: true),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturnRequests::route('/'),
            'view' => ViewReturnRequest::route('/{record}'),
        ];
    }
}
