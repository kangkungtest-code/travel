<?php

namespace App\Filament\Widgets;

use App\Support\Laporan;

class ProdukTerlaris extends GrafikDasar
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Produk terlaris (qty)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = Laporan::dariFilter($this->pageFilters)->produkTerlaris();

        return [
            'datasets' => [[
                'label' => 'Terjual',
                'data' => $data->values()->all(),
                'backgroundColor' => self::WARNA,
                'borderRadius' => 4,
                'barThickness' => 14,
            ]],
            'labels' => $data->keys()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return $this->opsiDasar(horizontal: true);
    }
}
