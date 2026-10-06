<?php

namespace App\Filament\Resources\ReturnRequests\Pages;

use App\Actions\Retur\ProsesReturAction;
use App\Exceptions\TokoException;
use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Models\ReturnRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewReturnRequest extends ViewRecord
{
    protected static string $resource = ReturnRequestResource::class;

    private function jalankan(callable $aksi, string $berhasil): void
    {
        try {
            $aksi(app(ProsesReturAction::class));
        } catch (TokoException $e) {
            Notification::make()->danger()->title('Retur tidak diubah')->body($e->getMessage())->send();

            return;
        }

        $this->getRecord()->refresh();
        Notification::make()->success()->title($berhasil)->body('Pembeli diberi tahu lewat email.')->send();
    }

    protected function getHeaderActions(): array
    {
        $status = fn () => $this->getRecord()->status;

        return [
            Action::make('setujui')
                ->label('Setujui')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->visible(fn () => $status() === ReturnRequest::STATUS_DIAJUKAN)
                ->schema([Textarea::make('catatan')->label('Catatan untuk pembeli (opsional)')])
                ->modalDescription('Pembeli akan diminta mengirim barang ke alamat retur toko dan mengisi nomor resi.')
                ->action(fn (array $data) => $this->jalankan(fn ($p) => $p->setujui($this->getRecord(), $data['catatan'] ?? null), 'Retur disetujui')),

            Action::make('tolak')
                ->label('Tolak')
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger')
                ->visible(fn () => $status() === ReturnRequest::STATUS_DIAJUKAN)
                ->schema([Textarea::make('catatan')->label('Alasan penolakan (dikirim ke pembeli)')->required()])
                ->action(fn (array $data) => $this->jalankan(fn ($p) => $p->tolak($this->getRecord(), $data['catatan']), 'Retur ditolak')),

            Action::make('selesaikan')
                ->label('Barang diterima, selesaikan')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn () => $status() === ReturnRequest::STATUS_DISETUJUI)
                ->schema([
                    Radio::make('penyelesaian')->options([
                        'refund' => 'Refund ke pembeli',
                        'ganti_barang' => 'Kirim barang pengganti',
                    ])->required(),
                    Textarea::make('catatan')->label('Catatan untuk pembeli (opsional)'),
                ])
                ->modalDescription('Kalau dipilih refund, sistem mencoba refund penuh lewat payment gateway (PayPal / QRIS). Virtual Account dan pembayaran manual perlu direfund manual.')
                ->action(function (array $data) {
                    $this->jalankan(fn ($p) => $p->selesaikan($this->getRecord(), $data['penyelesaian'], $data['catatan'] ?? null), 'Retur selesai');

                    if ($data['penyelesaian'] === 'refund' && $this->getRecord()->status === ReturnRequest::STATUS_SELESAI) {
                        try {
                            $pay = app(\App\Actions\Pembayaran\RefundPembayaranAction::class)->execute($this->getRecord()->order, 'Retur: '.$this->getRecord()->alasan);
                            Notification::make()->success()->title('Refund diproses')->body("ID refund: {$pay->refund_id}")->send();
                        } catch (TokoException $e) {
                            Notification::make()->warning()->title('Refund manual diperlukan')->body($e->getMessage())->persistent()->send();
                        }
                    }
                }),
        ];
    }
}
