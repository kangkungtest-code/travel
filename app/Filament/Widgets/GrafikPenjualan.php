<?php

namespace App\Filament\Widgets;

use App\Support\Laporan;
use Filament\Support\RawJs;
use Illuminate\Support\Carbon;

class GrafikPenjualan extends GrafikDasar
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Penjualan harian (IDR)';

    protected ?string $description = 'Berdasarkan tanggal bayar.';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $data = Laporan::dariFilter($this->pageFilters)->penjualanHarian();

        return [
            'datasets' => [[
                'label' => 'Penjualan',
                'data' => $data->values()->all(),
                'borderColor' => self::WARNA,
                'backgroundColor' => self::WARNA,
                'borderWidth' => 2,
                'pointRadius' => 0,
                'pointHoverRadius' => 5,
                'tension' => 0,
            ]],
            'labels' => $data->keys()->map(fn ($d) => Carbon::parse($d)->locale('id')->isoFormat('D MMM'))->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->opsiRupiah();
    }
}
