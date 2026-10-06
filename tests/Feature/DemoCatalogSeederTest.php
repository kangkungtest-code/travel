<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockHistory;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_katalog_demo_terisi_dan_idempotent(): void
    {
        Storage::fake('public');
        // Katalog contoh bawaan, apa pun isi toko/profil.php di branch ini.
        config(['toko.katalog' => null]);
        $this->seed([RoleAndPermissionSeeder::class, StockLocationSeeder::class]);

        $this->seed(DemoCatalogSeeder::class);

        $jumlahProduk = count((require database_path('data/katalog-demo.php'))['produk']);
        $this->assertSame($jumlahProduk, Product::count());
        $this->assertGreaterThan(50, ProductVariant::count());
        $this->assertSame(2, ExchangeRate::count());
        $this->assertGreaterThan(0, Faq::count());

        $kaos = ProductVariant::where('sku', 'KAOS-BASIC-HITAM-S')->firstOrFail();
        $this->assertSame('Basic Combed Cotton Tee', $kaos->product->getTranslation('nama_terjemahan', 'en'));
        $this->assertSame('精梳棉基本款T恤', $kaos->product->getTranslation('nama_terjemahan', 'zh_TW'));
        $this->assertEquals(['Warna' => 'Hitam', 'Ukuran' => 'S'], $kaos->opsi);

        $foto = ProductImage::firstOrFail();
        Storage::disk('public')->assertExists($foto->path);

        // Stok awal tercatat sebagai riwayat restock.
        $this->assertGreaterThan(0, StockHistory::where('alasan', 'restock')->count());

        // Jalankan ulang: tidak ada duplikat.
        $this->seed(DemoCatalogSeeder::class);
        $this->assertSame($jumlahProduk, Product::count());
        $this->assertSame(2, ExchangeRate::count());
    }

    /** Katalog dari profil toko (branch toko lain bisa punya file & foto sendiri). */
    public function test_katalog_dari_profil_toko_terisi(): void
    {
        Storage::fake('public');
        $this->seed([RoleAndPermissionSeeder::class, StockLocationSeeder::class, DemoCatalogSeeder::class]);

        $data = require DemoCatalogSeeder::fileData();
        $this->assertSame(count($data['produk']), Product::count());
        $this->assertSame(collect($data['produk'])->sum(fn ($p) => count($p['warna'])), ProductImage::count());
        ProductImage::all()->each(fn ($f) => Storage::disk('public')->assertExists($f->path));
    }

    public function test_foto_asli_dari_folder_toko(): void
    {
        Storage::fake('public');
        $file = base_path('toko/foto/_uji.png');
        @mkdir(dirname($file), 0777, true);
        $img = imagecreatetruecolor(50, 50);
        imagepng($img, $file);
        $katalog = base_path('toko/_uji-katalog.php');
        file_put_contents($katalog, '<?php $d = require __DIR__."/../database/data/katalog-demo.php"; $p = $d["produk"][0]; $p["foto"] = [$p["warna"][0] => "_uji.png"]; $d["produk"] = [$p]; return $d;');

        try {
            config(['toko.katalog' => '_uji-katalog.php']);
            $this->seed([RoleAndPermissionSeeder::class, StockLocationSeeder::class, DemoCatalogSeeder::class]);

            $this->assertSame(1, Product::count());
            [$w, $h] = getimagesizefromstring(Storage::disk('public')->get(ProductImage::orderBy('urutan')->firstOrFail()->path));
            $this->assertSame([50, 50], [$w, $h]); // foto asli, bukan siluet 1200px
        } finally {
            @unlink($file);
            @unlink($katalog);
        }
    }
}
