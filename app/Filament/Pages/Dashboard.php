<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Dashboard = laporan dasar dengan filter periode (default 30 hari terakhir). */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashboard';

    /**
     * Laporan toko hanya untuk orang toko. Super Admin (tanpa izin laporan.lihat) yang membuka
     * /admin langsung diarahkan ke halaman Fitur & paket; widget penjualan tidak dimuat.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return self::bolehLihatLaporan();
    }

    private static function bolehLihatLaporan(): bool
    {
        return (bool) \Filament\Facades\Filament::auth()->user()?->hasPermissionTo('laporan.lihat');
    }

    public function mount(): void
    {
        if (! self::bolehLihatLaporan()) {
            $this->redirect(FiturPaket::canAccess() ? FiturPaket::getUrl() : '/', navigate: false);
        }
    }

    public function getWidgets(): array
    {
        // Dashboard travel. Widget e-commerce (produk, stok, kategori) tetap ada sebagai kelas
        // tapi tidak ditampilkan.
        return self::bolehLihatLaporan() ? [
            \App\Filament\Widgets\RingkasanSewa::class,
            \App\Filament\Widgets\JadwalArmada::class,
            \App\Filament\Widgets\GrafikPenjualan::class,
            \App\Filament\Widgets\UtilisasiArmada::class,
            \App\Filament\Widgets\PendapatanKendaraan::class,
            \App\Filament\Widgets\StatusOrder::class,
            \App\Filament\Widgets\PenjualanPerMetode::class,
        ] : [];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    DatePicker::make('dari')->label('Dari')->default(now(config('toko.zona_waktu'))->subDays(29)->toDateString())->maxDate(fn ($get) => $get('sampai')),
                    DatePicker::make('sampai')->label('Sampai')->default(now(config('toko.zona_waktu'))->toDateString()),
                ]),
        ]);
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
