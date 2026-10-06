<?php

namespace App\Filament\Resources\Kendaraan\Pages;

use App\Filament\Resources\Kendaraan\KendaraanResource;
use App\Models\TipeKendaraan;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditKendaraan extends EditRecord
{
    protected static string $resource = KendaraanResource::class;

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
            DeleteAction::make()
                ->modalDescription('Kendaraan dan fotonya dihapus. Tidak bisa dihapus selama masih punya unit.')
                ->before(function (TipeKendaraan $record, DeleteAction $action) {
                    if ($record->unit()->exists()) {
                        Notification::make()->danger()
                            ->title('Masih ada unit')
                            ->body('Hapus atau nonaktifkan unitnya dulu. Untuk menyembunyikan dari situs, matikan "Tampil di situs".')
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
