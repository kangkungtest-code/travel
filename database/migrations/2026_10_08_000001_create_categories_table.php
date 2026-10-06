<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Kategori jadi data sendiri (3 bahasa, slug, urutan) supaya toko tidak terikat
     * ke jenis barang tertentu. Teks kategori lama di products dipindahkan ke sini,
     * terjemahannya diambil dari lang/*.json yang dulu dipakai.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('nama_terjemahan');
            $table->string('slug', 120)->unique();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('category_id')->nullable()->after('slug')->constrained()->nullOnDelete();
        });

        $kamus = fn (string $l) => is_file(lang_path("{$l}.json")) ? (json_decode(file_get_contents(lang_path("{$l}.json")), true) ?: []) : [];
        $en = $kamus('en');
        $zh = $kamus('zh_TW');

        $lama = DB::table('products')->whereNotNull('kategori')->where('kategori', '!=', '')
            ->distinct()->orderBy('kategori')->pluck('kategori');

        $dipakai = [];
        foreach ($lama->values() as $i => $nama) {
            $nama_en = $en[$nama] ?? $nama;
            $slug = Str::slug($nama_en) ?: Str::slug($nama) ?: 'kategori';
            for ($n = 2, $dasar = $slug; in_array($slug, $dipakai, true); $n++) {
                $slug = "{$dasar}-{$n}";
            }
            $dipakai[] = $slug;

            $id = (string) Str::uuid7();
            DB::table('categories')->insert([
                'id' => $id,
                'nama_terjemahan' => json_encode(array_filter(['id' => $nama, 'en' => $nama_en, 'zh_TW' => $zh[$nama] ?? null]), JSON_UNESCAPED_UNICODE),
                'slug' => $slug,
                'urutan' => $i + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('products')->where('kategori', $nama)->update(['category_id' => $id]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['kategori']);
            $table->dropColumn('kategori');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('kategori')->nullable()->index();
        });

        foreach (DB::table('categories')->get() as $c) {
            $nama = json_decode($c->nama_terjemahan, true) ?: [];
            DB::table('products')->where('category_id', $c->id)->update(['kategori' => $nama['id'] ?? reset($nama)]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('categories');
    }
};
