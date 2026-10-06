<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Data operasional booking: sopir yang ditugaskan, serah terima & pengembalian kendaraan. */
    public function up(): void
    {
        Schema::table('booking_sewa', function (Blueprint $table) {
            $table->json('sopir')->nullable()->after('dokumen');
            $table->json('serah_terima')->nullable()->after('sopir');
            $table->json('pengembalian')->nullable()->after('serah_terima');
        });
    }

    public function down(): void
    {
        Schema::table('booking_sewa', function (Blueprint $table) {
            $table->dropColumn(['sopir', 'serah_terima', 'pengembalian']);
        });
    }
};
