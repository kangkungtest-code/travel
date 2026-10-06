<?php

namespace App\Filament\Widgets;

use App\Filament\Support\LabelAdmin;
use App\Support\Laporan;

class StatusOrder extends GrafikDasar
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Status order (dibuat di periode ini)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $jumlah = Laporan::dariFilter($this->pageFilters)->statusOrder();
        $label = collect(LabelAdmin::STATUS_ORDER);

        return [
            'datasets' => [[
                'label' => 'Order',
                'data' => $label->keys()->map(fn ($s) => $jumlah[$s] ?? 0)->all(),
                'backgroundColor' => self::WARNA,
                'borderRadius' => 4,
                'barThickness' => 14,
            ]],
            'labels' => $label->values()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return $this->opsiDasar(horizontal: true);
    }
}
