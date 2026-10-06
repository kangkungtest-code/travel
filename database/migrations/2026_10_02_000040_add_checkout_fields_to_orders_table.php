<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan untuk checkout (di luar ERD awal):
 * - nomor: nomor order yang mudah dibaca pembeli
 * - alamat_snapshot: alamat saat order dibuat (alamat di profil bisa berubah/dihapus)
 * - *_idr: total dalam base currency untuk laporan
 * - kadaluarsa_pada: batas bayar, dipakai scheduler untuk melepas stok
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('nomor', 30)->nullable()->unique()->after('id');
            $table->json('alamat_snapshot')->nullable()->after('address_id');
            $table->decimal('subtotal_idr', 15, 2)->default(0)->after('total');
            $table->decimal('ongkir_idr', 15, 2)->default(0)->after('subtotal_idr');
            $table->decimal('total_idr', 15, 2)->default(0)->after('ongkir_idr');
            $table->unsignedInteger('berat_gram')->default(0)->after('total_idr');
            $table->timestamp('kadaluarsa_pada')->nullable()->index()->after('resi');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('address_id')->nullable()->change();
            $table->foreign('address_id')->references('id')->on('addresses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->dropColumn(['nomor', 'alamat_snapshot', 'subtotal_idr', 'ongkir_idr', 'total_idr', 'berat_gram', 'kadaluarsa_pada']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('address_id')->references('id')->on('addresses')->restrictOnDelete();
        });
    }
};
