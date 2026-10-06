<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('path')->comment('Path WebP di disk public; thumbnail = <nama>-thumb.webp');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
