<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Halaman kebijakan toko (privasi, syarat, retur, pengiriman), 3 bahasa, isi Markdown. */
    public function up(): void
    {
        Schema::create('halaman_kebijakan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 50)->unique();
            $table->json('judul_terjemahan');
            $table->json('isi_terjemahan');
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('halaman_kebijakan');
    }
};
