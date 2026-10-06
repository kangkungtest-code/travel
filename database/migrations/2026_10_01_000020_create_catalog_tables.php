<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('nama_terjemahan')->comment('{"id":"...","en":"...","zh-TW":"..."}');
            $table->json('deskripsi_terjemahan')->nullable();
            $table->string('kategori')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->json('opsi')->nullable()->comment('{"ukuran":"M","warna":"Hitam"}');
            $table->decimal('harga_idr', 15, 2);
            $table->unsignedInteger('berat_gram')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->integer('jumlah')->default(0);
            $table->integer('jumlah_reserved')->default(0);
            $table->timestamps();

            $table->unique(['variant_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
