<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarif sewa per tipe kendaraan & mode (lepas kunci / dengan sopir), dalam IDR.
     * Tarif musim = kenaikan persen untuk rentang tanggal, berlaku semua kendaraan.
     */
    public function up(): void
    {
        Schema::create('tarif', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tipe_kendaraan_id')->constrained('tipe_kendaraan')->cascadeOnDelete();
            $table->string('mode', 20);
            $table->decimal('harga_harian', 15, 2);
            $table->decimal('harga_12jam', 15, 2)->nullable();
            $table->decimal('harga_per_jam', 15, 2)->nullable();
            $table->unsignedSmallInteger('minimal_jam')->default(12);
            $table->boolean('termasuk_bbm')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tipe_kendaraan_id', 'mode']);
        });

        Schema::create('tarif_musim', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 100);
            $table->date('mulai');
            $table->date('selesai');
            $table->decimal('kenaikan_persen', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['mulai', 'selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_musim');
        Schema::dropIfExists('tarif');
    }
};
