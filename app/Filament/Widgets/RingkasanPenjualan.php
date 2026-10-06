<?php

namespace App\Filament\Widgets;

use App\Filament\Support\LabelAdmin;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\Stock;
use App\Support\Laporan;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanPenjualan extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $r = Laporan::dariFilter($this->pageFilters)->ringkasan();

        return [
            Stat::make('Penjualan', LabelAdmin::rupiah($r['penjualan']))->description('Order terbayar di periode ini, termasuk ongkir'),
            Stat::make('Order terbayar', (string) $r['jumlah'])->description('Rata-rata '.LabelAdmin::rupiah($r['rata']).' per order'),
            Stat::make('Perlu diproses', (string) Order::where('status', Order::STATUS_DIBAYAR)->count())->description('Sudah dibayar, belum dikemas'),
            Stat::make('Menunggu bayar', (string) Order::where('status', Order::STATUS_MENUNGGU_PEMBAYARAN)->count())->description('Stok sedang ditahan'),
            ...(! \App\Support\Fitur::aktif('retur') ? [] : [Stat::make('Retur baru', (string) ReturnRequest::where('status', ReturnRequest::STATUS_DIAJUKAN)->count())->description('Perlu ditinjau')]),
            Stat::make('Stok menipis', (string) Stock::whereRaw('(jumlah - jumlah_reserved) <= 5')->count())->description('Varian dengan stok tersedia ≤ 5'),
        ];
    }
}
