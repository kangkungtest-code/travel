<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Riwayat perubahan status order + waktu tiap tahap (di luar ERD awal). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->string('dari', 30)->nullable();
            $table->string('ke', 30);
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete()->comment('Siapa yang mengubah; null = sistem');
            $table->string('catatan', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('dibayar_pada')->nullable()->index()->after('kadaluarsa_pada');
            $table->timestamp('dikirim_pada')->nullable()->after('dibayar_pada');
            $table->timestamp('selesai_pada')->nullable()->after('dikirim_pada');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->text('catatan_admin')->nullable()->after('resi_kembali');
            $table->string('penyelesaian', 20)->nullable()->after('catatan_admin')->comment('refund / ganti_barang');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn(['catatan_admin', 'penyelesaian']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['dibayar_pada', 'dikirim_pada', 'selesai_pada']);
        });
        Schema::dropIfExists('order_status_histories');
    }
};
