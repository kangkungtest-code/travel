<?php

namespace App\Filament\Resources\Stocks;

use App\Actions\Stok\UbahStokAction;
use App\Filament\Resources\Stocks\Pages\ManageStocks;
use App\Models\Stock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use UnitEnum;

/** Ringkasan stok semua varian + ubah stok cepat (restock / koreksi). */
class StockResource extends Resource
{
    /** Travel: menu toko barang disembunyikan (config travel.menu_toko_barang). */
    public static function shouldRegisterNavigation(): bool
    {
        return config('travel.menu_toko_barang') && parent::shouldRegisterNavigation();
    }

    protected static ?string $model = Stock::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCube;

    protected static string | UnitEnum | null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'stok';

    protected static ?string $pluralModelLabel = 'stok';

    protected static ?string $navigationLabel = 'Stok';

    public const BATAS_MENIPIS = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('variant.product')
                ->select('stocks.*')
                ->selectRaw('(jumlah - jumlah_reserved) as tersedia'))
            ->defaultSort('tersedia')
            ->columns([
                TextColumn::make('variant.product.nama_terjemahan')->label('Produk')
                    ->state(fn (Stock $s) => $s->variant?->product?->getTranslation('nama_terjemahan', 'id'))
                    ->description(fn (Stock $s) => collect($s->variant?->opsi ?? [])->map(fn ($n, $k) => "{$k}: {$n}")->implode(', ')),
                TextColumn::make('variant.sku')->label('SKU')->searchable(),
                TextColumn::make('jumlah')->label('Fisik')->sortable(),
                TextColumn::make('jumlah_reserved')->label('Di-reserve')->sortable()
                    ->tooltip('Sedang ditahan order yang belum dibayar'),
                TextColumn::make('tersedia')->label('Tersedia')->sortable()->badge()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= self::BATAS_MENIPIS ? 'warning' : 'success')),
            ])
            ->filters([
                Filter::make('menipis')->label('Menipis (≤ '.self::BATAS_MENIPIS.')')
                    ->query(fn (Builder $query) => $query->whereRaw('(jumlah - jumlah_reserved) <= ?', [self::BATAS_MENIPIS])),
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options(fn () => \App\Models\Category::query()->orderBy('urutan')->get()->mapWithKeys(fn ($c) => [$c->id => $c->nama('id')])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $k) => $q->whereHas('variant.product', fn ($p) => $p->where('category_id', $k)))),
            ])
            ->recordActions([
                Action::make('ubahStok')
                    ->label('Ubah stok')
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->visible(fn () => (bool) \Filament\Facades\Filament::auth()->user()?->hasPermissionTo('stok.edit'))
                    ->schema([
                        Radio::make('alasan')->options([
                            UbahStokAction::ALASAN_RESTOCK => 'Restock (barang masuk)',
                            UbahStokAction::ALASAN_KOREKSI => 'Koreksi (hitung ulang / rusak / hilang)',
                        ])->default(UbahStokAction::ALASAN_RESTOCK)->required(),
                        TextInput::make('perubahan')->label('Jumlah')->helperText('Angka negatif untuk mengurangi.')->integer()->required()->notIn([0]),
                    ])
                    ->action(function (Stock $record, array $data, Action $action) {
                        try {
                            app(UbahStokAction::class)->execute($record->variant, (int) $data['perubahan'], $data['alasan'], $record->location);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->danger()->title('Stok tidak diubah')->body($e->getMessage())->send();
                            $action->halt();
                        }
                        Notification::make()->success()->title('Stok diperbarui')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStocks::route('/')];
    }
}
