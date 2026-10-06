<?php

namespace App\Filament\Widgets;

use App\Filament\Support\LabelAdmin;
use App\Models\BookingSewa;
use App\Models\Order;
use App\Support\Laporan;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class RingkasanSewa extends StatsOverviewWidget
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
        $laporan = Laporan::dariFilter($this->pageFilters);
        $r = $laporan->ringkasan();
        $tz = config('toko.zona_waktu');
        $hariIni = [now($tz)->startOfDay()->utc(), now($tz)->endOfDay()->utc()];
        $jadwal = fn (string $kolom, array $status) => BookingSewa::query()
            ->whereBetween($kolom, $hariIni)
            ->whereHas('order', fn (Builder $o) => $o->whereIn('status', $status))
            ->count();

        return [
            Stat::make('Pendapatan', LabelAdmin::rupiah($r['penjualan']))->description('Booking terbayar di periode ini'),
            Stat::make('Booking terbayar', (string) $r['jumlah'])->description('Rata-rata '.LabelAdmin::rupiah($r['rata']).' per booking'),
            Stat::make('Utilisasi armada', str_replace('.', ',', (string) $laporan->utilisasiTotal()).'%')->description('Jam tersewa dibanding kapasitas unit siap'),
            Stat::make('Perlu dikonfirmasi', (string) Order::where('status', Order::STATUS_DIBAYAR)->count())->description('Sudah dibayar, belum dikonfirmasi'),
            Stat::make('Ambil hari ini', (string) $jadwal('mulai', [Order::STATUS_DIBAYAR, Order::STATUS_DIPROSES]))->description('Kendaraan yang harus diserahkan'),
            Stat::make('Kembali hari ini', (string) $jadwal('selesai', [Order::STATUS_DIKIRIM]))->description('Kendaraan yang dijadwalkan kembali'),
        ];
    }
}
