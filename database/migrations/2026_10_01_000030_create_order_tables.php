<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('address_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('menunggu_pembayaran')->index()
                ->comment('menunggu_pembayaran / dibayar / diproses / dikirim / selesai / kadaluarsa / dibatalkan');
            $table->string('sumber_order', 30)->default('online');
            $table->string('mata_uang', 3);
            $table->decimal('kurs_terpakai', 20, 10)->comment('Snapshot kurs IDR -> mata_uang saat order dibuat');
            $table->decimal('ongkir', 15, 2)->default(0);
            $table->decimal('tarif_pajak_terpakai', 5, 2)->nullable()->comment('Persen, disiapkan untuk nanti');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);
            $table->string('resi')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('harga_saat_itu', 15, 2);
            $table->timestamps();
        });

        Schema::create('stock_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('perubahan')->comment('Bisa negatif');
            $table->string('alasan', 20)->comment('reserve / kurangi / lepas / restock');
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 20)->comment('paypal / xendit_qris / xendit_va');
            $table->string('status', 20)->default('pending')->comment('pending / berhasil / gagal / direfund');
            $table->string('mata_uang', 3);
            $table->decimal('jumlah', 15, 2);
            $table->string('transaksi_id_eksternal')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->restrictOnDelete();
            $table->text('alasan');
            $table->string('foto_bukti')->nullable();
            $table->string('status', 20)->default('diajukan')->comment('diajukan / disetujui / ditolak / selesai');
            $table->string('resi_kembali')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('stock_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
