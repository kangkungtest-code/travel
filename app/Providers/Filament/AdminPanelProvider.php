<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Masuk::class)
            ->profile(\App\Filament\Pages\ProfilSaya::class, isSimple: false)
            ->authGuard('admin')
            ->brandName(config('toko.nama').' Admin')
            // Logo toko (kalau ada) di samping nama, bukan menggantikan nama.
            ->brandLogo(fn () => ($logo = \App\Support\Tema::logo())
                ? new \Illuminate\Support\HtmlString('<span style="display:inline-flex;align-items:center;gap:.6rem;font-weight:700"><img src="'.e($logo).'" alt="" style="height:2rem;width:2rem;object-fit:contain;border-radius:4px">'.e(config('toko.nama').' Admin').'</span>')
                : null)
            ->favicon(fn () => \App\Support\Tema::logo())
            ->colors([
                'primary' => config('toko.warna_admin') ? Color::hex(config('toko.warna_admin')) : Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->navigationGroups(['Penjualan', 'Armada', 'Katalog', 'Konten', 'Pengaturan'])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
