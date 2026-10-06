<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Token FCM HP admin. Terikat ke token login (Sanctum) supaya ikut hilang saat keluar. */
    public function up(): void
    {
        Schema::create('perangkat_admin', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index();
            $table->string('fcm_token', 512)->unique();
            $table->string('platform', 20)->default('android');
            $table->timestamp('terakhir_aktif')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perangkat_admin');
    }
};
