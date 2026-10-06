<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Order\BatalkanOrderAction;
use App\Actions\Order\UbahStatusOrderAction;
use App\Exceptions\TokoException;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use App\Actions\Sewa\OperasionalBookingAction;
use App\Filament\Support\LabelAdmin;
use App\Models\UnitKendaraan;
use App\Support\Ketersediaan;
use Filament\Forms\Components\Select;
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
        return ($this->sewa() ? 'Booking ' : 'Order ').$this->getRecord()->nomor;
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

    private function sewa(): bool
    {
        return (bool) $this->getRecord()->bookingSewa;
    }

    private function operasional(): OperasionalBookingAction
    {
        return app(OperasionalBookingAction::class);
    }

    /** Pilihan unit untuk booking ini: unit tipe yang sama yang bebas di jadwalnya (+ unit sekarang). */
    private function pilihanUnit(): array
    {
        $b = $this->getRecord()->bookingSewa;

        return Ketersediaan::filterBebas(UnitKendaraan::query()->where('tipe_kendaraan_id', $b->tipe_kendaraan_id), $b->mulai, $b->selesai, null, $b->order_id)
            ->with('lokasi')
            ->orderBy('plat_nomor')
            ->get()
            ->mapWithKeys(fn (UnitKendaraan $u) => [$u->id => $u->plat_nomor.' — '.$u->lokasi?->nama('id').($u->warna ? ', '.$u->warna : '')])
            ->all();
    }

    /** @return array<int, Action> */
    private function aksiSewa(): array
    {
        $bbm = config('travel.level_bbm');

        return [
            Action::make('konfirmasiBooking')
                ->label('Konfirmasi booking')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->visible(fn () => $this->sewa() && $this->terlihat(Order::STATUS_DIBAYAR))
                ->modalDescription('Periksa dokumen penyewa, pastikan unit, lalu konfirmasi. Penyewa melihat plat nomor setelah ini.')
                ->fillForm(fn () => ['unit_kendaraan_id' => $this->getRecord()->bookingSewa->unit_kendaraan_id])
                ->schema(fn () => [
                    Select::make('unit_kendaraan_id')->label('Unit')->options($this->pilihanUnit())->required()->native(false),
                    TextInput::make('sopir_nama')->label('Nama sopir')->maxLength(100)
                        ->visible($this->getRecord()->bookingSewa->mode === 'sopir')->required($this->getRecord()->bookingSewa->mode === 'sopir'),
                    TextInput::make('sopir_telepon')->label('Telepon sopir')->tel()->maxLength(30)
                        ->visible($this->getRecord()->bookingSewa->mode === 'sopir'),
                    Textarea::make('catatan')->label('Catatan internal')->rows(2),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => $this->operasional()->konfirmasi($this->getRecord(), Filament::auth()->user(), $data), 'Booking dikonfirmasi')),

            Action::make('gantiUnit')
                ->label('Ganti unit')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->visible(fn () => $this->sewa() && $this->bolehUbah() && in_array($this->getRecord()->status, [Order::STATUS_MENUNGGU_PEMBAYARAN, Order::STATUS_DIPROSES], true))
                ->fillForm(fn () => ['unit_kendaraan_id' => $this->getRecord()->bookingSewa->unit_kendaraan_id])
                ->schema(fn () => [Select::make('unit_kendaraan_id')->label('Unit')->options($this->pilihanUnit())->required()->native(false)])
                ->action(fn (array $data) => $this->jalankan(fn () => $this->operasional()->gantiUnit($this->getRecord(), $data['unit_kendaraan_id']), 'Unit diganti')),

            Action::make('serahTerima')
                ->label('Serah terima')
                ->icon(Heroicon::OutlinedKey)
                ->visible(fn () => $this->sewa() && $this->terlihat(Order::STATUS_DIPROSES))
                ->modalDescription('Catat kondisi saat kendaraan diserahkan ke penyewa.')
                ->schema([
                    TextInput::make('km')->label('Odometer (km)')->integer()->minValue(0)->required(),
                    Select::make('bbm')->label('BBM')->options($bbm)->default('penuh')->required()->native(false),
                    Textarea::make('catatan')->label('Kondisi / catatan')->rows(3),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => $this->operasional()->serahTerima($this->getRecord(), Filament::auth()->user(), $data), 'Kendaraan diserahkan')),

            Action::make('pengembalian')
                ->label('Pengembalian')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('success')
                ->visible(fn () => $this->sewa() && $this->terlihat(Order::STATUS_DIKIRIM))
                ->modalDescription(function () {
                    $t = OperasionalBookingAction::keterlambatan($this->getRecord()->bookingSewa);

                    return $t['jam'] > 0
                        ? "Terlambat {$t['jam']} jam dari jadwal. Saran denda: ".LabelAdmin::rupiah($t['denda']).' (bisa diubah).'
                        : 'Kendaraan kembali tepat waktu.';
                })
                ->fillForm(fn () => [
                    'denda_telat' => OperasionalBookingAction::keterlambatan($this->getRecord()->bookingSewa)['denda'],
                    'bbm' => $this->getRecord()->bookingSewa->serah_terima['bbm'] ?? 'penuh',
                ])
                ->schema([
                    TextInput::make('km')->label('Odometer (km)')->integer()->minValue(0)->required()
                        ->helperText(fn () => 'Km saat diserahkan: '.number_format((int) ($this->getRecord()->bookingSewa->serah_terima['km'] ?? 0), 0, ',', '.')),
                    Select::make('bbm')->label('BBM')->options($bbm)->required()->native(false),
                    TextInput::make('denda_telat')->label('Denda keterlambatan')->prefix('Rp')->numeric()->minValue(0),
                    TextInput::make('biaya_lain')->label('Biaya lain (kerusakan, BBM, kebersihan)')->prefix('Rp')->numeric()->minValue(0),
                    Textarea::make('catatan')->label('Kondisi / catatan')->rows(3),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => $this->operasional()->pengembalian($this->getRecord(), Filament::auth()->user(), $data), 'Sewa selesai')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->aksiSewa(),

            Action::make('konfirmasiBayar')
                ->label('Konfirmasi pembayaran manual')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->visible(fn () => $this->terlihat(Order::STATUS_MENUNGGU_PEMBAYARAN))
                ->modalDescription(fn () => $this->sewa()
                    ? 'Pakai hanya kalau pembayaran sudah dipastikan masuk di luar sistem (mis. transfer manual).'
                    : 'Pakai hanya kalau pembayaran sudah dipastikan masuk di luar sistem (mis. transfer manual). Stok akan dikurangi permanen.')
                ->schema([Textarea::make('catatan')->label('Catatan (mis. bukti transfer)')->required()])
                ->action(fn (array $data) => $this->jalankan(fn () => $this->ubah(Order::STATUS_DIBAYAR, $data), 'Order ditandai dibayar')),

            Action::make('proses')
                ->hidden(fn () => $this->sewa())
                ->label('Proses order')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->visible(fn () => $this->terlihat(Order::STATUS_DIBAYAR))
                ->requiresConfirmation()
                ->modalDescription('Tandai order sedang dikemas.')
                ->action(fn () => $this->jalankan(fn () => $this->ubah(Order::STATUS_DIPROSES), 'Order diproses')),

            Action::make('kirim')
                ->hidden(fn () => $this->sewa())
                ->label('Kirim')
                ->icon(Heroicon::OutlinedTruck)
                ->visible(fn () => $this->terlihat(Order::STATUS_DIPROSES))
                ->schema([TextInput::make('resi')->label('Nomor resi')->required()->maxLength(100)])
                ->modalDescription('Pembeli akan menerima email berisi nomor resi.')
                ->action(fn (array $data) => $this->jalankan(fn () => $this->ubah(Order::STATUS_DIKIRIM, $data), 'Order dikirim')),

            Action::make('selesai')
                ->hidden(fn () => $this->sewa())
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
                    $this->sewa() ? 'Booking dibatalkan, unit dilepas' : 'Order dibatalkan, stok dilepas',
                )),
        ];
    }
}
