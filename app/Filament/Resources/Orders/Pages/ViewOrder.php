<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Order\BatalkanOrderAction;
use App\Actions\Order\UbahStatusOrderAction;
use App\Exceptions\TokoException;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'Order '.$this->getRecord()->nomor;
    }

    private function bolehUbah(): bool
    {
        return (bool) Filament::auth()->user()?->hasPermissionTo('order.ubah_status');
    }

    private function jalankan(callable $aksi, string $berhasil): void
    {
        try {
            $aksi();
        } catch (TokoException $e) {
            Notification::make()->danger()->title('Status tidak diubah')->body($e->getMessage())->send();

            return;
        }

        $this->getRecord()->refresh();
        Notification::make()->success()->title($berhasil)->send();
    }

    private function ubah(string $ke, array $data = []): void
    {
        app(UbahStatusOrderAction::class)->execute($this->getRecord(), $ke, Filament::auth()->user(), $data);
    }

    private function terlihat(string $status): bool
    {
        return $this->bolehUbah() && $this->getRecord()->status === $status;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('konfirmasiBayar')
                ->label('Konfirmasi pembayaran manual')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->visible(fn () => $this->terlihat(Order::STATUS_MENUNGGU_PEMBAYARAN))
                ->modalDescription('Pakai hanya kalau pembayaran sudah dipastikan masuk di luar sistem (mis. transfer manual). Stok akan dikurangi permanen.')
                ->schema([Textarea::make('catatan')->label('Catatan (mis. bukti transfer)')->required()])
                ->action(fn (array $data) => $this->jalankan(fn () => $this->ubah(Order::STATUS_DIBAYAR, $data), 'Order ditandai dibayar')),

            Action::make('proses')
                ->label('Proses order')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->visible(fn () => $this->terlihat(Order::STATUS_DIBAYAR))
                ->requiresConfirmation()
                ->modalDescription('Tandai order sedang dikemas.')
                ->action(fn () => $this->jalankan(fn () => $this->ubah(Order::STATUS_DIPROSES), 'Order diproses')),

            Action::make('kirim')
                ->label('Kirim')
                ->icon(Heroicon::OutlinedTruck)
                ->visible(fn () => $this->terlihat(Order::STATUS_DIPROSES))
                ->schema([TextInput::make('resi')->label('Nomor resi')->required()->maxLength(100)])
                ->modalDescription('Pembeli akan menerima email berisi nomor resi.')
                ->action(fn (array $data) => $this->jalankan(fn () => $this->ubah(Order::STATUS_DIKIRIM, $data), 'Order dikirim')),

            Action::make('selesai')
                ->label('Tandai selesai')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn () => $this->terlihat(Order::STATUS_DIKIRIM))
                ->requiresConfirmation()
                ->modalDescription('Barang sudah diterima pembeli. Batas waktu retur mulai dihitung.')
                ->action(fn () => $this->jalankan(fn () => $this->ubah(Order::STATUS_SELESAI), 'Order selesai')),

            Action::make('batalkan')
                ->label('Batalkan')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn () => $this->terlihat(Order::STATUS_MENUNGGU_PEMBAYARAN))
                ->schema([Textarea::make('catatan')->label('Alasan pembatalan')->required()])
                ->action(fn (array $data) => $this->jalankan(
                    fn () => app(BatalkanOrderAction::class)->execute($this->getRecord(), Filament::auth()->user(), $data['catatan']),
                    'Order dibatalkan, stok dilepas',
                )),
        ];
    }
}
