<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan untuk pembayaran online:
 * - kurs_terpakai / jumlah_idr: mata uang bayar bisa beda dari mata uang order (PayPal USD/TWD, Xendit IDR)
 * - url_bayar / data_bayar: link persetujuan PayPal, QR string QRIS, nomor VA
 * - status: pending / berhasil / gagal / kadaluarsa / direfund
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('kurs_terpakai', 20, 10)->nullable()->after('jumlah');
            $table->decimal('jumlah_idr', 15, 2)->default(0)->after('kurs_terpakai');
            $table->text('url_bayar')->nullable()->after('transaksi_id_eksternal');
            $table->json('data_bayar')->nullable()->after('url_bayar');
            $table->string('id_capture')->nullable()->after('data_bayar')->comment('PayPal capture id / Xendit payment id, dipakai untuk refund');
            $table->timestamp('dibayar_pada')->nullable()->after('id_capture');
            $table->timestamp('kadaluarsa_pada')->nullable()->after('dibayar_pada');
            $table->string('refund_id')->nullable()->after('kadaluarsa_pada');
            $table->string('catatan', 500)->nullable()->after('refund_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['kurs_terpakai', 'jumlah_idr', 'url_bayar', 'data_bayar', 'id_capture', 'dibayar_pada', 'kadaluarsa_pada', 'refund_id', 'catatan']);
        });
    }
};
