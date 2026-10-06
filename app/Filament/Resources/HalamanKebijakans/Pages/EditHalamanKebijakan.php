<?php

namespace App\Filament\Resources\HalamanKebijakans\Pages;

use App\Filament\Resources\HalamanKebijakans\HalamanKebijakanResource;
use App\Models\HalamanKebijakan;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditHalamanKebijakan extends EditRecord
{
    protected static string $resource = HalamanKebijakanResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Isi form dengan semua bahasa, bukan hanya bahasa aktif.
        foreach ($this->getRecord()->getTranslatableAttributes() as $attribute) {
            $data[$attribute] = $this->getRecord()->getTranslations($attribute);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lihat')->label('Lihat di toko')->icon(Heroicon::OutlinedEye)->color('gray')
                ->url(fn (HalamanKebijakan $record) => route('kebijakan', $record))->openUrlInNewTab(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
