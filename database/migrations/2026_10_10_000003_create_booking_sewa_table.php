<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detail sewa kendaraan untuk sebuah order. Booking = order (pembayaran, status,
     * riwayat, notifikasi admin, panel pesanan & API dipakai ulang) + satu baris ini.
     * Waktu disimpan UTC. Unit dialokasikan saat booking dibuat (dengan lock), admin
     * bisa memindahkannya.
     */
    public function up(): void
    {
        Schema::create('booking_sewa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('tipe_kendaraan_id')->constrained('tipe_kendaraan')->restrictOnDelete();
            $table->foreignUuid('unit_kendaraan_id')->nullable()->constrained('unit_kendaraan')->nullOnDelete();
            $table->foreignUuid('lokasi_id')->constrained('lokasi')->restrictOnDelete();
            $table->string('mode', 20);
            $table->dateTime('mulai');
            $table->dateTime('selesai');
            $table->string('nama_penyewa', 150);
            $table->string('telepon', 30);
            $table->text('catatan')->nullable();
            $table->json('rincian');
            $table->json('dokumen')->nullable();
            $table->timestamps();
            $table->index(['unit_kendaraan_id', 'mulai', 'selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_sewa');
    }
};
