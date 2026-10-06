<?php

namespace App\Filament\Widgets;

use App\Support\Laporan;

class UtilisasiArmada extends GrafikDasar
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Utilisasi per kendaraan (%)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = Laporan::dariFilter($this->pageFilters)->utilisasiArmada();

        return [
            'datasets' => [[
                'label' => 'Utilisasi %',
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
        $opsi = $this->opsiDasar(horizontal: true);
        $opsi['scales']['x']['max'] = 100;

        return $opsi;
    }
}
