<?php

namespace App\Filament\Resources\StockHistories;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\StockHistories\Pages\ListStockHistories;
use App\Models\StockHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Riwayat pergerakan stok (hanya baca). */
class StockHistoryResource extends Resource
{
    /** Travel: menu toko barang disembunyikan (config travel.menu_toko_barang). */
    public static function shouldRegisterNavigation(): bool
    {
        return config('travel.menu_toko_barang') && parent::shouldRegisterNavigation();
    }

    protected static ?string $model = StockHistory::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static string | UnitEnum | null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'riwayat stok';

    protected static ?string $pluralModelLabel = 'riwayat stok';

    protected static ?string $navigationLabel = 'Riwayat stok';

    public const ALASAN = [
        'restock' => 'Restock',
        'koreksi' => 'Koreksi',
        'reserve' => 'Reserve (order dibuat)',
        'lepas' => 'Lepas (order batal/kadaluarsa)',
        'kurangi' => 'Terjual (order dibayar)',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variant.product', 'order']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i', config('toko.zona_waktu'))->sortable(),
                TextColumn::make('variant.sku')->label('SKU')->searchable()
                    ->description(fn (StockHistory $h) => $h->variant?->product?->getTranslation('nama_terjemahan', 'id')),
                TextColumn::make('perubahan')->label('Perubahan')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? "+{$state}" : (string) $state)
                    ->color(fn (int $state) => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('alasan')->badge()->formatStateUsing(fn (string $state) => self::ALASAN[$state] ?? $state),
                TextColumn::make('order.nomor')->label('Order')->placeholder('—')
                    ->url(fn (StockHistory $h) => $h->order ? OrderResource::getUrl('view', ['record' => $h->order]) : null),
            ])
            ->filters([
                SelectFilter::make('alasan')->options(self::ALASAN),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockHistories::route('/')];
    }
}
