<?php

namespace Tests\Feature;

use App\Actions\Stok\UbahStokAction;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\User;
use App\Support\ProductImageStorage;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class KatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['ADMIN_EMAIL'] = $_SERVER['ADMIN_EMAIL'] = 'owner@toko.test';
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = 'rahasia-123';
        $this->seed(DatabaseSeeder::class);

        Storage::fake('public');
        Filament::setCurrentPanel('admin');

        // ADMIN_EMAIL = Super Admin (tanpa akses data toko); tes ini memakai akun Owner.
        $this->admin = User::factory()->create(['email' => 'pemilik-toko@toko.test']);
        $this->admin->assignRole('Owner');
        $this->actingAs($this->admin, 'admin');
    }

    private function buatProduk(): Product
    {
        return Product::create([
            'nama_terjemahan' => ['en' => 'Black T-Shirt', 'id' => 'Kaos Hitam'],
            'category_id' => $this->kategoriKaos()->id,
            'is_active' => true,
        ]);
    }

    private function kategoriKaos(): \App\Models\Category
    {
        return \App\Models\Category::firstOrCreate(['slug' => 't-shirts'], ['nama_terjemahan' => ['id' => 'Kaos', 'en' => 'T-shirts']]);
    }

    public function test_halaman_daftar_produk_bisa_dibuka(): void
    {
        $this->buatProduk();

        $this->get(ProductResource::getUrl('index'))->assertOk()->assertSee('Kaos Hitam');
    }

    public function test_customer_tidak_bisa_buka_produk(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');

        $this->get(ProductResource::getUrl('index'))->assertForbidden();
    }

    public function test_admin_bisa_membuat_produk_multi_bahasa(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'nama_terjemahan' => ['en' => 'Black T-Shirt', 'id' => 'Kaos Hitam', 'zh_TW' => '黑色T恤'],
                'deskripsi_terjemahan' => ['en' => 'Cotton', 'id' => 'Katun', 'zh_TW' => null],
                'category_id' => $this->kategoriKaos()->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::firstOrFail();
        $this->assertSame('t-shirts', $product->category->slug);
        $this->assertSame('Kaos Hitam', $product->getTranslation('nama_terjemahan', 'id'));
        $this->assertSame('黑色T恤', $product->getTranslation('nama_terjemahan', 'zh_TW'));
        // Bahasa kosong jatuh ke English.
        $this->assertSame('Cotton', $product->getTranslation('deskripsi_terjemahan', 'zh_TW'));
    }

    public function test_nama_english_dan_indonesia_wajib(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'nama_terjemahan' => ['en' => null, 'id' => null, 'zh_TW' => '黑色T恤'],
            ])
            ->call('create')
            ->assertHasFormErrors(['nama_terjemahan.en' => 'required', 'nama_terjemahan.id' => 'required']);

        $this->assertSame(0, Product::count());
    }

    public function test_halaman_edit_menampilkan_semua_bahasa(): void
    {
        $product = $this->buatProduk();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertOk()
            ->assertSchemaStateSet([
                'nama_terjemahan.en' => 'Black T-Shirt',
                'nama_terjemahan.id' => 'Kaos Hitam',
            ]);
    }

    public function test_tambah_varian_dengan_stok_awal(): void
    {
        $product = $this->buatProduk();

        Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'sku' => 'KAOS-M-HITAM',
                'harga_idr' => 120000,
                'opsi' => ['Ukuran' => 'M', 'Warna' => 'Hitam'],
                'berat_gram' => 200,
                'stok_awal' => 10,
            ])
            ->assertHasNoFormErrors();

        $variant = ProductVariant::where('sku', 'KAOS-M-HITAM')->firstOrFail();
        $this->assertEquals(['Ukuran' => 'M', 'Warna' => 'Hitam'], $variant->opsi); // MySQL JSON tidak menjaga urutan key
        $this->assertSame(10, $variant->stokTersedia());
        $this->assertSame(1, StockHistory::where('variant_id', $variant->id)->where('alasan', 'restock')->count());
    }

    public function test_ubah_stok_lewat_tombol_dan_tidak_bisa_di_bawah_reserved(): void
    {
        $product = $this->buatProduk();
        $variant = app(\App\Actions\Katalog\BuatVarianAction::class)->execute($product, [
            'sku' => 'KAOS-L', 'harga_idr' => 120000, 'berat_gram' => 200, 'stok_awal' => 10,
        ]);

        Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])
            ->callAction(TestAction::make('ubahStok')->table($variant), [
                'alasan' => UbahStokAction::ALASAN_KOREKSI,
                'perubahan' => -3,
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(7, $variant->fresh()->stokTersedia());

        Stock::where('variant_id', $variant->id)->update(['jumlah_reserved' => 5]);

        $this->expectException(InvalidArgumentException::class);
        app(UbahStokAction::class)->execute($variant->fresh(), -5, UbahStokAction::ALASAN_KOREKSI);
    }

    public function test_foto_dikonversi_ke_webp_dan_diperkecil(): void
    {
        $path = app(ProductImageStorage::class)->store(UploadedFile::fake()->image('foto.jpg', 3000, 2000));

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists([$path, ProductImageStorage::thumbPath($path)]);

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([1600, 1067], [$w, $h]);

        [$tw] = getimagesizefromstring(Storage::disk('public')->get(ProductImageStorage::thumbPath($path)));
        $this->assertSame(400, $tw);
    }

    public function test_file_foto_ikut_terhapus_bersama_produk(): void
    {
        $product = $this->buatProduk();
        $path = app(ProductImageStorage::class)->store(UploadedFile::fake()->image('foto.png', 800, 800));
        $image = $product->images()->create(['path' => $path, 'urutan' => 1]);

        $product->delete();

        $this->assertSame(0, ProductImage::count());
        Storage::disk('public')->assertMissing([$path, ProductImageStorage::thumbPath($path)]);
        $this->assertNotNull($image);
    }

    public function test_kelola_kategori_tiga_bahasa(): void
    {
        Livewire::test(\App\Filament\Resources\Categories\Pages\ManageCategories::class)
            ->callAction('create', ['nama_terjemahan' => ['id' => 'Sepatu', 'en' => 'Shoes', 'zh_TW' => '鞋子'], 'slug' => null, 'is_active' => true])
            ->assertHasNoActionErrors();

        $c = \App\Models\Category::where('slug', 'shoes')->firstOrFail();
        $this->assertSame('鞋子', $c->nama('zh_TW'));

        // Slug tidak boleh dobel / berformat salah.
        Livewire::test(\App\Filament\Resources\Categories\Pages\ManageCategories::class)
            ->callAction('create', ['nama_terjemahan' => ['id' => 'X', 'en' => 'X'], 'slug' => 'shoes'])
            ->assertHasActionErrors(['slug' => 'unique']);
        Livewire::test(\App\Filament\Resources\Categories\Pages\ManageCategories::class)
            ->callAction('create', ['nama_terjemahan' => ['id' => 'X', 'en' => 'X'], 'slug' => 'Bukan Slug'])
            ->assertHasActionErrors(['slug']);

        // Hapus kategori: produknya tetap ada, tanpa kategori.
        $p = $this->buatProduk();
        $p->update(['category_id' => $c->id]);
        $c->delete();
        $this->assertNull($p->fresh()->category_id);
    }
}
