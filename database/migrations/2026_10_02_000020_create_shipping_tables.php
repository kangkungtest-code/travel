<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ongkir flat per zona (kumpulan negara) dan rentang berat. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama');
            $table->json('negara')->comment('Daftar kode negara ISO alpha-2, mis. ["ID"]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('berat_min_gram');
            $table->unsignedInteger('berat_max_gram');
            $table->decimal('tarif_idr', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zones');
    }
};
