<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Armada sewa kendaraan:
     *  - lokasi          : pool / kantor tempat kendaraan diambil & dikembalikan
     *  - tipe_kendaraan  : yang dipilih pembeli (setara produk), mis. "Toyota Avanza"
     *  - foto_kendaraan  : foto per tipe (WebP + thumbnail, pola sama dengan foto produk)
     *  - unit_kendaraan  : mobil fisik per tipe (plat nomor), dialokasikan ke booking
     */
    public function up(): void
    {
        Schema::create('lokasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('nama_terjemahan');
            $table->string('kota', 100);
            $table->text('alamat')->nullable();
            $table->string('url_peta', 500)->nullable();
            $table->string('jam_operasional', 100)->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tipe_kendaraan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('nama_terjemahan');
            $table->string('slug', 180)->unique();
            $table->json('deskripsi_terjemahan')->nullable();
            $table->string('jenis', 20)->index();
            $table->unsignedSmallInteger('kursi');
            $table->string('transmisi', 20);
            $table->string('bbm', 20);
            $table->unsignedSmallInteger('bagasi')->nullable();
            $table->json('fasilitas')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('foto_kendaraan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tipe_kendaraan_id')->constrained('tipe_kendaraan')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('unit_kendaraan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tipe_kendaraan_id')->constrained('tipe_kendaraan')->restrictOnDelete();
            $table->foreignUuid('lokasi_id')->constrained('lokasi')->restrictOnDelete();
            $table->string('plat_nomor', 20)->unique();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->string('warna', 50)->nullable();
            $table->string('status', 20)->default('siap')->index();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kendaraan');
        Schema::dropIfExists('foto_kendaraan');
        Schema::dropIfExists('tipe_kendaraan');
        Schema::dropIfExists('lokasi');
    }
};
