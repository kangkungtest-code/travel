<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Data penerima yang dibutuhkan kurir (di luar ERD awal). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('nama_penerima')->default('')->after('label');
            $table->string('telepon', 30)->default('')->after('nama_penerima');
            $table->string('kota', 100)->default('')->after('negara');
            $table->string('kode_pos', 20)->default('')->after('kota');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['nama_penerima', 'telepon', 'kota', 'kode_pos']);
        });
    }
};
