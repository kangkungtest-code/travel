<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Alamat produk yang mudah dibaca (/produk/kaos-basic) untuk SEO; dibuat otomatis dari nama English. */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', 190)->nullable()->unique()->after('id');
        });

        DB::table('products')->orderBy('created_at')->get(['id', 'nama_terjemahan'])->each(function ($p) {
            $nama = json_decode($p->nama_terjemahan, true) ?: [];
            DB::table('products')->where('id', $p->id)->update([
                'slug' => Product::slugUnik($nama['en'] ?? reset($nama) ?: 'produk', $p->id),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
