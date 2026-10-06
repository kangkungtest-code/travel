<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\BookingSewa;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Ambil & kembali dalam 3 hari ke depan (booking yang sudah dibayar). */
class JadwalArmada extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Jadwal 3 hari ke depan';

    public function table(Table $table): Table
    {
        $tz = config('toko.zona_waktu');
        $awal = now($tz)->startOfDay()->utc();
        $akhir = now($tz)->addDays(2)->endOfDay()->utc();

        return $table
            ->query(fn () => BookingSewa::query()
                ->with(['order', 'tipe', 'unit', 'lokasi'])
                ->whereHas('order', fn (Builder $o) => $o->whereIn('status', [Order::STATUS_DIBAYAR, Order::STATUS_DIPROSES, Order::STATUS_DIKIRIM]))
                ->where(fn (Builder $q) => $q->whereBetween('mulai', [$awal, $akhir])->orWhereBetween('selesai', [$awal, $akhir]))
                ->orderBy('mulai'))
            ->paginated([10])
            ->emptyStateHeading('Tidak ada jadwal ambil/kembali dalam 3 hari')
            ->columns([
                TextColumn::make('kegiatan')->label('')
                    ->state(fn (BookingSewa $b) => $b->order->status === Order::STATUS_DIKIRIM ? 'Kembali' : 'Ambil')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Ambil' ? 'info' : 'success'),
                TextColumn::make('waktu')->label('Waktu')
                    ->state(fn (BookingSewa $b) => ($b->order->status === Order::STATUS_DIKIRIM ? $b->selesai : $b->mulai)->timezone($tz)->translatedFormat('D d M, H:i')),
                TextColumn::make('kendaraan')->label('Kendaraan')
                    ->state(fn (BookingSewa $b) => $b->tipe?->nama('id'))
                    ->description(fn (BookingSewa $b) => $b->unit?->plat_nomor ?? 'tanpa unit'),
                TextColumn::make('lokasi')->label('Lokasi')->state(fn (BookingSewa $b) => $b->lokasi?->nama('id')),
                TextColumn::make('nama_penyewa')->label('Penyewa')->description(fn (BookingSewa $b) => $b->telepon),
                TextColumn::make('order.nomor')->label('Booking'),
            ])
            ->recordUrl(fn (BookingSewa $b) => OrderResource::getUrl('view', ['record' => $b->order]));
    }
}
