<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\SewaInfolist;
use App\Filament\Support\LabelAdmin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Kurs;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string | UnitEnum | null $navigationGroup = 'Penjualan';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'booking';

    protected static ?string $modelLabel = 'booking';

    protected static ?string $pluralModelLabel = 'booking';

    protected static ?string $navigationLabel = 'Booking';

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getNavigationBadge(): ?string
    {
        $n = Order::query()->where('status', Order::STATUS_DIBAYAR)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Sudah dibayar, perlu dikonfirmasi';
    }

    public static function uang(Order $o, $nilai): string
    {
        return app(Kurs::class)->formatNilai((float) $nilai, $o->mata_uang);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'bookingSewa.tipe', 'bookingSewa.unit']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nomor')->label('Nomor')->searchable()->weight('bold'),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y H:i', config('toko.zona_waktu'))->sortable(),
                TextColumn::make('user.nama_lengkap')->label('Penyewa')
                    ->state(fn (Order $o) => $o->bookingSewa?->nama_penyewa ?? $o->user?->nama_lengkap)
                    ->description(fn (Order $o) => $o->bookingSewa?->telepon ?? $o->user?->email)
                    ->searchable(['nama_lengkap', 'email']),
                TextColumn::make('kendaraan')->label('Kendaraan')
                    ->state(fn (Order $o) => $o->bookingSewa?->tipe?->nama('id'))
                    ->description(fn (Order $o) => $o->bookingSewa ? ($o->bookingSewa->unit?->plat_nomor ?? 'tanpa unit').' · '.\App\Support\Spesifikasi::label('mode', $o->bookingSewa->mode, 'id') : null)
                    ->placeholder('—'),
                TextColumn::make('jadwal')->label('Jadwal')
                    ->state(fn (Order $o) => $o->bookingSewa ? $o->bookingSewa->mulai->timezone(config('toko.zona_waktu'))->format('d M H:i').' → '.$o->bookingSewa->selesai->timezone(config('toko.zona_waktu'))->format('d M H:i') : null)
                    ->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => LabelAdmin::STATUS_ORDER[$state] ?? $state)
                    ->color(fn (string $state) => LabelAdmin::WARNA_ORDER[$state] ?? 'gray'),
                TextColumn::make('total')->label('Total')
                    ->formatStateUsing(fn (Order $o) => self::uang($o, $o->total))
                    ->description(fn (Order $o) => $o->mata_uang !== 'IDR' ? LabelAdmin::rupiah($o->total_idr) : null),
                TextColumn::make('negara')->label('Negara')
                    ->state(fn (Order $o) => $o->alamat_snapshot['negara'] ?? null)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(LabelAdmin::STATUS_ORDER)->multiple(),
                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($d, config('toko.zona_waktu'))->startOfDay()->utc()))
                        ->when($data['sampai'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($d, config('toko.zona_waktu'))->endOfDay()->utc()))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Ringkasan')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->badge()
                            ->formatStateUsing(fn (string $state) => LabelAdmin::STATUS_ORDER[$state] ?? $state)
                            ->color(fn (string $state) => LabelAdmin::WARNA_ORDER[$state] ?? 'gray'),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i', config('toko.zona_waktu')),
                        TextEntry::make('kadaluarsa_pada')->label('Batas bayar')->dateTime('d M Y H:i', config('toko.zona_waktu'))
                            ->visible(fn (Order $o) => $o->status === Order::STATUS_MENUNGGU_PEMBAYARAN),
                        TextEntry::make('dibayar_pada')->label('Dibayar')->dateTime('d M Y H:i', config('toko.zona_waktu'))->placeholder('—'),
                        TextEntry::make('resi')->label('Resi')->placeholder('—')->copyable()
                            ->visible(fn (Order $o) => ! $o->bookingSewa),
                        TextEntry::make('user.nama_lengkap')->label('Akun')
                            ->helperText(fn (Order $o) => $o->user?->email),
                    ]),

                Section::make('Pembayaran')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('subtotal')->formatStateUsing(fn (Order $o) => self::uang($o, $o->subtotal))
                            ->visible(fn (Order $o) => ! $o->bookingSewa),
                        TextEntry::make('rincian_sewa')->label('Rincian sewa')
                            ->visible(fn (Order $o) => (bool) $o->bookingSewa)
                            ->state(fn (Order $o) => self::rincianSewa($o))
                            ->extraAttributes(['style' => 'white-space: pre-line']),
                        TextEntry::make('ongkir')->label('Ongkir')
                            ->visible(fn (Order $o) => ! $o->bookingSewa)
                            ->formatStateUsing(fn (Order $o) => self::uang($o, $o->ongkir))
                            ->helperText(fn (Order $o) => number_format($o->berat_gram / 1000, 2).' kg'),
                        TextEntry::make('total')->weight('bold')
                            ->formatStateUsing(fn (Order $o) => self::uang($o, $o->total))
                            ->helperText(fn (Order $o) => $o->mata_uang !== 'IDR'
                                ? LabelAdmin::rupiah($o->total_idr).' · kurs '.rtrim(rtrim(number_format((float) $o->kurs_terpakai, 8, '.', ''), '0'), '.')
                                : null),
                    ]),

                SewaInfolist::section(),

                Section::make('Barang')
                    ->visible(fn (Order $o) => ! $o->bookingSewa)
                    ->columnSpan(2)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('variant.product.nama_terjemahan')->label('Produk')
                                    ->formatStateUsing(fn (OrderItem $record) => $record->variant?->product?->getTranslation('nama_terjemahan', 'id') ?? '—'),
                                TextEntry::make('variant.sku')->label('SKU'),
                                TextEntry::make('qty')->label('Qty'),
                                TextEntry::make('harga_saat_itu')->label('Harga satuan')
                                    ->formatStateUsing(fn ($state) => LabelAdmin::rupiah($state)),
                            ]),
                    ]),

                Section::make('Kirim ke')
                    ->visible(fn (Order $o) => ! $o->bookingSewa)
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('alamat')
                            ->hiddenLabel()
                            ->state(fn (Order $o) => collect([
                                ($o->alamat_snapshot['nama_penerima'] ?? '').' ('.($o->alamat_snapshot['telepon'] ?? '').')',
                                $o->alamat_snapshot['detail_alamat'] ?? '',
                                trim(($o->alamat_snapshot['kota'] ?? '').' '.($o->alamat_snapshot['kode_pos'] ?? '')),
                                config('toko.negara.'.($o->alamat_snapshot['negara'] ?? ''), $o->alamat_snapshot['negara'] ?? ''),
                            ])->filter()->implode("\n"))
                            ->extraAttributes(['style' => 'white-space: pre-line']),
                    ]),

                Section::make('Pembayaran online')
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Order $o) => $o->payments()->exists())
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->hiddenLabel()
                            ->columns(5)
                            ->schema([
                                TextEntry::make('gateway')->label('Metode')
                                    ->formatStateUsing(fn (string $state, $record) => \App\Payments\MetodePembayaran::label($state).(($record->data_bayar['bank'] ?? null) ? ' '.$record->data_bayar['bank'] : '')),
                                TextEntry::make('status')->badge()
                                    ->color(fn (string $state) => match ($state) { 'berhasil' => 'success', 'pending' => 'warning', 'direfund' => 'info', default => 'gray' }),
                                TextEntry::make('jumlah')->label('Jumlah')
                                    ->formatStateUsing(fn ($state, $record) => app(Kurs::class)->formatNilai((float) $state, $record->mata_uang)),
                                TextEntry::make('transaksi_id_eksternal')->label('ID gateway')->placeholder('—')->copyable(),
                                TextEntry::make('dibayar_pada')->label('Dibayar')->dateTime('d M Y H:i', config('toko.zona_waktu'))->placeholder('—'),
                                TextEntry::make('catatan')->label('Catatan')->placeholder('—')->columnSpanFull()
                                    ->visible(fn ($record) => filled($record?->catatan)),
                            ]),
                    ]),

                Section::make('Riwayat status')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('statusHistories')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('ke')->label('Status')
                                    ->formatStateUsing(fn (string $state) => LabelAdmin::STATUS_ORDER[$state] ?? $state),
                                TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i', config('toko.zona_waktu')),
                                TextEntry::make('user.nama_lengkap')->label('Oleh')->placeholder('Sistem'),
                                TextEntry::make('catatan')->label('Catatan')->placeholder('—'),
                            ]),
                    ]),
            ]);
    }

    /** Rincian harga sewa (IDR) dari snapshot booking. */
    public static function rincianSewa(Order $o): string
    {
        $r = $o->bookingSewa?->rincian ?? [];
        $rp = fn ($n) => LabelAdmin::rupiah($n);

        return collect([
            ($r['hari'] ?? 0) ? $r['hari'].' hari × '.$rp($r['harga_hari']) : null,
            ($r['sisa_jam'] ?? 0) ? 'Tambahan '.$r['sisa_jam'].' jam: '.$rp($r['harga_sisa']) : null,
            ($r['tambahan_musim'] ?? 0) ? 'Musim ramai: +'.$rp($r['tambahan_musim']) : null,
            ($r['jam_ditagih'] ?? 0) > ($r['durasi_jam'] ?? 0) ? 'Minimal sewa '.$r['jam_ditagih'].' jam' : null,
        ])->filter()->implode("\n");
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
