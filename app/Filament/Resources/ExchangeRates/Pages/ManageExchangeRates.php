<?php

namespace App\Filament\Resources\ExchangeRates\Pages;

use App\Filament\Resources\ExchangeRates\ExchangeRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExchangeRates extends ManageRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah kurs baru')
                ->mutateDataUsing(function (array $data): array {
                    // Admin mengisi "1 USD = Rp16.500"; disimpan sebagai rate 1 IDR = 1/16.500 USD.
                    $perUnit = (float) ($data['per_unit'] ?? 0);
                    unset($data['per_unit']);

                    return $data + [
                        'mata_uang_asal' => config('toko.base_currency'),
                        'rate' => $perUnit > 0 ? 1 / $perUnit : 0,
                        'sumber' => 'manual',
                    ];
                }),
        ];
    }
}
