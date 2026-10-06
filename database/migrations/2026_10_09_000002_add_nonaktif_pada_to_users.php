<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Akun admin/staf yang dinonaktifkan tidak bisa masuk panel & aplikasi admin (datanya tetap). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('nonaktif_pada')->nullable()->after('dihapus_pada');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nonaktif_pada');
        });
    }
};
