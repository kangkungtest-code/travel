<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - dihapus_pada: akun yang dihapus pembeli (data pribadi dianonimkan, baris tetap ada
     *   karena pesanan lama butuh relasinya untuk pembukuan).
     * - Verifikasi email mulai berlaku: akun yang sudah ada dianggap terverifikasi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('dihapus_pada')->nullable()->after('remember_token');
        });

        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dihapus_pada');
        });
    }
};
