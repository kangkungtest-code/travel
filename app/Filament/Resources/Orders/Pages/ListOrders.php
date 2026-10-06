<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $tab = fn (string $label, array $status) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $status))
            ->badge(Order::query()->whereIn('status', $status)->count() ?: null);

        return [
            'semua' => Tab::make('Semua'),
            'perlu_diproses' => $tab('Perlu dikonfirmasi', [Order::STATUS_DIBAYAR]),
            'menunggu' => $tab('Menunggu bayar', [Order::STATUS_MENUNGGU_PEMBAYARAN]),
            'diproses' => $tab('Dikonfirmasi', [Order::STATUS_DIPROSES]),
            'dikirim' => $tab('Berjalan', [Order::STATUS_DIKIRIM]),
            'selesai' => $tab('Selesai', [Order::STATUS_SELESAI]),
            'batal' => Tab::make('Batal / kadaluarsa')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [Order::STATUS_DIBATALKAN, Order::STATUS_KADALUARSA])),
        ];
    }
}
