<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Riwayat perubahan paket & fitur oleh Super Admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_fitur', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('paket_dari')->nullable();
            $table->unsignedTinyInteger('paket_ke');
            $table->json('dinyalakan');
            $table->json('dimatikan');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_fitur');
    }
};
