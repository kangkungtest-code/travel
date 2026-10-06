<?php

namespace App\Providers;

use App\Support\Keranjang;
use App\Support\Kurs;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu instance per request (Kurs menyimpan cache kurs).
        $this->app->scoped(Kurs::class);
        $this->app->scoped(Keranjang::class);
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        // Fitur bersaklar yang bekerja lewat config (bahasa, mata uang, negara kirim).
        \App\Support\Fitur::terapkanConfig();

        // Isi keranjang tamu pindah ke akun setelah pembeli masuk (email, daftar, atau login sosial).
        Event::listen(function (Login $event) {
            if ($event->guard === 'web') {
                app(Keranjang::class)->gabungkanKe($event->user);
            }
        });

        // Data header storefront: jumlah barang di keranjang & status login.
        View::composer('layouts.toko', function ($view) {
            $view->with([
                'jumlahKeranjang' => app(Keranjang::class)->jumlahBarang(),
                'pembeli' => Auth::guard('web')->user(),
            ]);
        });
    }
}
