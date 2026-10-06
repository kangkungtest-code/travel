<?php

use App\Http\Controllers\Auth\MasukController;
use App\Http\Controllers\Auth\VerifikasiController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SosialController;
use App\Http\Controllers\Toko\AkunController;
use App\Http\Controllers\Toko\AlamatController;
use App\Http\Controllers\Toko\ChatbotController;
use App\Http\Controllers\Toko\CheckoutController;
use App\Http\Controllers\Toko\KatalogController;
use App\Http\Controllers\Toko\KeranjangController;
use App\Http\Controllers\Toko\PembayaranController;
use App\Http\Controllers\Toko\PesananController;
use App\Http\Controllers\WebhookPembayaranController;
use App\Http\Controllers\Toko\PreferensiController;
use Illuminate\Support\Facades\Route;

// Katalog (tanpa login)
Route::get('/', [KatalogController::class, 'home'])->name('home');
Route::get('/produk', [KatalogController::class, 'index'])->name('produk.index');
Route::get('/produk/{product}', [KatalogController::class, 'show'])->name('produk.show');
Route::get('/faq', [KatalogController::class, 'faq'])->name('faq');
Route::get('/sitemap.xml', \App\Http\Controllers\Toko\SitemapController::class)->name('sitemap');
Route::get('/kebijakan/{halaman}', [KatalogController::class, 'kebijakan'])->name('kebijakan');

Route::get('/chatbot', [ChatbotController::class, 'mulai'])->middleware(['fitur:chatbot', 'throttle:30,1'])->name('chatbot');
Route::post('/chatbot', [ChatbotController::class, 'tanya'])->middleware(['fitur:chatbot', 'throttle:30,1']);

Route::post('/preferensi', [PreferensiController::class, 'update'])->middleware('throttle:30,1')->name('preferensi');

// Keranjang (tamu boleh)
Route::get('/keranjang', [KeranjangController::class, 'index'])->name('keranjang');
Route::post('/keranjang', [KeranjangController::class, 'tambah'])->middleware('throttle:60,1')->name('keranjang.tambah');
Route::patch('/keranjang/{item}', [KeranjangController::class, 'ubah'])->name('keranjang.ubah');
Route::delete('/keranjang/{item}', [KeranjangController::class, 'hapus'])->name('keranjang.hapus');

// Auth pembeli
Route::middleware('guest:web')->group(function () {
    Route::get('/masuk', [MasukController::class, 'formMasuk'])->name('login');
    Route::post('/masuk', [MasukController::class, 'masuk'])->middleware('throttle:20,1');
    Route::get('/daftar', [MasukController::class, 'formDaftar'])->name('daftar');
    Route::post('/daftar', [MasukController::class, 'daftar'])->middleware('throttle:10,1');

    Route::get('/lupa-password', [PasswordController::class, 'formLupa'])->name('password.request');
    Route::post('/lupa-password', [PasswordController::class, 'kirimLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'formReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');

    Route::get('/masuk/lengkapi-email', [SosialController::class, 'formEmail'])->name('masuk.sosial.email');
    Route::post('/masuk/lengkapi-email', [SosialController::class, 'simpanEmail'])->middleware('throttle:10,1');
    Route::get('/masuk/{provider}', [SosialController::class, 'redirect'])->whereIn('provider', ['google', 'line'])->name('masuk.sosial');
    Route::get('/masuk/{provider}/callback', [SosialController::class, 'callback'])->whereIn('provider', ['google', 'line'])->middleware('throttle:20,1')->name('masuk.sosial.callback');
});

Route::post('/keluar', [MasukController::class, 'keluar'])->middleware('auth:web')->name('keluar');

// Akun & checkout (wajib login)
Route::middleware('auth:web')->group(function () {
    Route::get('/akun', [AkunController::class, 'index'])->name('akun');
    Route::put('/akun/profil', [AkunController::class, 'updateProfil'])->name('akun.profil');
    Route::put('/akun/password', [AkunController::class, 'updatePassword'])->name('akun.password');
    Route::delete('/akun', [AkunController::class, 'hapus'])->middleware('throttle:5,1')->name('akun.hapus');

    Route::get('/email/verifikasi', [VerifikasiController::class, 'pemberitahuan'])->name('verification.notice');
    Route::get('/email/verifikasi/{id}/{hash}', [VerifikasiController::class, 'verifikasi'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verifikasi/kirim-ulang', [VerifikasiController::class, 'kirimUlang'])->middleware('throttle:3,1')->name('verification.send');

    Route::get('/akun/alamat/baru', [AlamatController::class, 'create'])->name('akun.alamat.create');
    Route::post('/akun/alamat', [AlamatController::class, 'store'])->name('akun.alamat.store');
    Route::get('/akun/alamat/{alamat}/ubah', [AlamatController::class, 'edit'])->name('akun.alamat.edit');
    Route::put('/akun/alamat/{alamat}', [AlamatController::class, 'update'])->name('akun.alamat.update');
    Route::delete('/akun/alamat/{alamat}', [AlamatController::class, 'destroy'])->name('akun.alamat.destroy');

    Route::get('/akun/pesanan', [PesananController::class, 'index'])->name('akun.pesanan');
    Route::get('/akun/pesanan/{order}', [PesananController::class, 'show'])->name('akun.pesanan.show');
    Route::post('/akun/pesanan/{order}/batal', [PesananController::class, 'batal'])->name('akun.pesanan.batal');
    Route::post('/akun/pesanan/{order}/retur', [PesananController::class, 'ajukanRetur'])->middleware(['fitur:retur', 'throttle:5,1'])->name('akun.pesanan.retur');
    Route::post('/akun/pesanan/{order}/bayar', [PembayaranController::class, 'bayar'])->middleware(['terverifikasi', 'throttle:10,1'])->name('akun.pesanan.bayar');
    Route::post('/akun/pesanan/{order}/simulasi', [PembayaranController::class, 'simulasi'])->middleware('throttle:10,1')->name('akun.pesanan.simulasi');
    Route::get('/akun/pesanan/{order}/status', [PembayaranController::class, 'status'])->middleware('throttle:30,1')->name('akun.pesanan.status');
    Route::get('/bayar/paypal/{payment}/kembali', [PembayaranController::class, 'paypalKembali'])->name('bayar.paypal.kembali');
    Route::get('/bayar/paypal/{payment}/batal', [PembayaranController::class, 'paypalBatal'])->name('bayar.paypal.batal');
    Route::put('/akun/retur/{retur}/resi', [PesananController::class, 'resiRetur'])->middleware('fitur:retur')->name('akun.retur.resi');

    Route::get('/checkout', [CheckoutController::class, 'show'])->middleware('terverifikasi')->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware(['terverifikasi', 'throttle:10,1']);
});

// Webhook payment gateway (tanpa CSRF & sesi; diverifikasi per gateway).
Route::post('/webhook/paypal', [WebhookPembayaranController::class, 'paypal'])->middleware('throttle:120,1')->name('webhook.paypal');
Route::post('/webhook/xendit', [WebhookPembayaranController::class, 'xendit'])->middleware('throttle:120,1')->name('webhook.xendit');
