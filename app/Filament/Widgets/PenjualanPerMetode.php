<?php

namespace App\Filament\Widgets;

use App\Support\Laporan;
use Filament\Support\RawJs;

class PenjualanPerMetode extends GrafikDasar
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Penjualan per metode bayar (IDR)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = Laporan::dariFilter($this->pageFilters)->perMetodeBayar();

        return [
            'datasets' => [[
                'label' => 'Penjualan',
                'data' => $data->values()->all(),
                'backgroundColor' => self::WARNA,
                'borderRadius' => 4,
                'barThickness' => 14,
            ]],
            'labels' => $data->keys()->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->opsiRupiah(horizontal: true);
    }
}
