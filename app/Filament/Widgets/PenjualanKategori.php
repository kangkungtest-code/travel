<?php

namespace App\Filament\Widgets;

use App\Support\Laporan;
use Filament\Support\RawJs;

class PenjualanKategori extends GrafikDasar
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Penjualan per kategori (IDR)';

    protected ?string $description = 'Nilai barang, tanpa ongkir.';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = Laporan::dariFilter($this->pageFilters)->penjualanPerKategori();

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
