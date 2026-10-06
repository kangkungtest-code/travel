<?php

namespace App\Filament\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Gaya grafik bersama: satu seri, satu warna, grid samar, tanpa legenda (judul sudah menamai serinya). */
abstract class GrafikDasar extends ChartWidget
{
    use InteractsWithPageFilters;

    public const WARNA = '#4a7f45';

    protected ?string $maxHeight = '280px';

    protected function opsiDasar(bool $horizontal = false, bool $rupiah = false): array
    {
        $sumbuNilai = [
            'beginAtZero' => true,
            'grid' => ['color' => 'rgba(127,127,127,0.15)'],
            'border' => ['display' => false],
            'ticks' => ['precision' => 0],
        ];
        $sumbuKategori = ['grid' => ['display' => false], 'border' => ['display' => false]];

        return [
            'indexAxis' => $horizontal ? 'y' : 'x',
            'plugins' => ['legend' => ['display' => false]],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => $horizontal
                ? ['x' => $sumbuNilai, 'y' => $sumbuKategori]
                : ['x' => $sumbuKategori, 'y' => $sumbuNilai],
        ];
    }

    /** Format angka rupiah ringkas di sumbu & tooltip (mis. Rp1,2 jt). */
    protected function opsiRupiah(bool $horizontal = false): RawJs
    {
        $sumbu = $horizontal ? 'x' : 'y';

        return RawJs::make(<<<JS
            {
                indexAxis: '{$this->indexAxis($horizontal)}',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => 'Rp' + Number(c.raw).toLocaleString('id-ID') } },
                },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    {$sumbu}: {
                        beginAtZero: true,
                        grid: { color: 'rgba(127,127,127,0.15)' },
                        border: { display: false },
                        ticks: { callback: (v) => v >= 1e6 ? 'Rp' + (v / 1e6).toLocaleString('id-ID') + ' jt' : (v >= 1e3 ? 'Rp' + (v / 1e3) + ' rb' : 'Rp' + v) },
                    },
                    {$this->sumbuLain($horizontal)}: { grid: { display: false }, border: { display: false } },
                },
            }
        JS);
    }

    private function indexAxis(bool $horizontal): string
    {
        return $horizontal ? 'y' : 'x';
    }

    private function sumbuLain(bool $horizontal): string
    {
        return $horizontal ? 'y' : 'x';
    }
}
