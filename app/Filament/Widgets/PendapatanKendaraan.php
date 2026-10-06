<?php

namespace App\Filament\Widgets;

use App\Support\Laporan;
use Filament\Support\RawJs;

class PendapatanKendaraan extends GrafikDasar
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Pendapatan per kendaraan';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = Laporan::dariFilter($this->pageFilters)->pendapatanPerKendaraan();

        return [
            'datasets' => [[
                'label' => 'Pendapatan',
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
