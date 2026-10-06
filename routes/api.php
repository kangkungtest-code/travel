<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\NotifikasiController;
use App\Http\Controllers\Api\V1\Admin\PerangkatController;
use App\Http\Controllers\Api\V1\Admin\PesananController;
use App\Http\Controllers\Api\V1\Admin\ReturController;
use App\Http\Controllers\Api\V1\Admin\StokController;
use Illuminate\Support\Facades\Route;

/*
| API v1 — /api/v1/...
| Bagian /admin dipakai aplikasi Flutter admin. Dokumentasi: docs/api-v1.md
*/
Route::prefix('v1')->group(function () {
    Route::get('/ping', fn () => ['status' => 'ok', 'versi' => 'v1']);

    Route::prefix('admin')->middleware([\App\Http\Middleware\BahasaAdminApi::class, 'fitur:aplikasi_mobile'])->group(function () {
        Route::post('/masuk', [AuthController::class, 'masuk'])->middleware('throttle:10,1');

        Route::middleware(['auth:sanctum', 'admin.api', 'throttle:120,1'])->group(function () {
            Route::get('/saya', [AuthController::class, 'saya']);
            Route::post('/keluar', [AuthController::class, 'keluar']);

            Route::post('/perangkat', [PerangkatController::class, 'simpan']);
            Route::delete('/perangkat', [PerangkatController::class, 'hapus']);

            Route::get('/notifikasi', [NotifikasiController::class, 'index']);
            Route::post('/notifikasi/dibaca-semua', [NotifikasiController::class, 'dibacaSemua']);
            Route::post('/notifikasi/{id}/dibaca', [NotifikasiController::class, 'dibaca']);

            Route::get('/dashboard', DashboardController::class)->middleware('izin:laporan.lihat');

            Route::middleware('izin:order.lihat')->group(function () {
                Route::get('/pesanan', [PesananController::class, 'index']);
                Route::get('/pesanan/jumlah', [PesananController::class, 'jumlah']);
                Route::get('/pesanan/{pesanan}', [PesananController::class, 'show']);
            });
            Route::middleware('izin:order.ubah_status')->group(function () {
                Route::post('/pesanan/{pesanan}/konfirmasi-bayar', [PesananController::class, 'konfirmasiBayar']);
                Route::post('/pesanan/{pesanan}/proses', [PesananController::class, 'proses']);
                Route::post('/pesanan/{pesanan}/kirim', [PesananController::class, 'kirim']);
                Route::post('/pesanan/{pesanan}/selesai', [PesananController::class, 'selesai']);
                Route::post('/pesanan/{pesanan}/batalkan', [PesananController::class, 'batalkan']);
            });

            Route::middleware('izin:stok.edit')->group(function () {
                Route::get('/stok', [StokController::class, 'index']);
                Route::get('/stok/{varian}', [StokController::class, 'show']);
                Route::get('/stok/{varian}/riwayat', [StokController::class, 'riwayat']);
                Route::post('/stok/{varian}', [StokController::class, 'ubah']);
            });

            Route::middleware(['fitur:retur', 'izin:retur.kelola'])->group(function () {
                Route::get('/retur', [ReturController::class, 'index']);
                Route::get('/retur/{retur}', [ReturController::class, 'show']);
                Route::post('/retur/{retur}/setujui', [ReturController::class, 'setujui']);
                Route::post('/retur/{retur}/tolak', [ReturController::class, 'tolak']);
                Route::post('/retur/{retur}/selesaikan', [ReturController::class, 'selesaikan']);
            });
        });
    });
});
