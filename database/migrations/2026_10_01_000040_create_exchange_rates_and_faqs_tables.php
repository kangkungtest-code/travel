<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('mata_uang_asal', 3);
            $table->string('mata_uang_tujuan', 3);
            $table->decimal('rate', 20, 10);
            $table->decimal('margin_persen', 5, 2)->default(0);
            $table->string('sumber', 10)->default('manual')->comment('manual / api');
            $table->timestamp('berlaku_dari');
            $table->timestamps();

            $table->index(['mata_uang_asal', 'mata_uang_tujuan', 'berlaku_dari'], 'exchange_rates_pair_berlaku_index');
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('pertanyaan_terjemahan');
            $table->json('jawaban_terjemahan');
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('exchange_rates');
    }
};
