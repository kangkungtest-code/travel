<?php

namespace Tests\Feature;

use App\Actions\Katalog\BuatVarianAction;
use App\Models\ExchangeRate;
use App\Models\Faq;
use App\Models\Product;
use App\Models\User;
use App\Support\Kurs;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Product $kaos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StockLocationSeeder::class);

        ExchangeRate::create(['mata_uang_asal' => 'IDR', 'mata_uang_tujuan' => 'USD', 'rate' => 0.00006, 'margin_persen' => 0, 'sumber' => 'manual', 'berlaku_dari' => now()->subDay()]);
        ExchangeRate::create(['mata_uang_asal' => 'IDR', 'mata_uang_tujuan' => 'TWD', 'rate' => 0.002, 'margin_persen' => 2, 'sumber' => 'manual', 'berlaku_dari' => now()->subDay()]);

        $this->kaos = Product::create([
            'nama_terjemahan' => ['en' => 'Black T-Shirt', 'id' => 'Kaos Hitam', 'zh_TW' => '黑色T恤'],
            'deskripsi_terjemahan' => ['en' => 'Soft cotton', 'id' => 'Katun lembut'],
            'category_id' => \App\Models\Category::firstOrCreate(['slug' => 't-shirts'], ['nama_terjemahan' => ['id' => 'Kaos', 'en' => 'T-shirts', 'zh_TW' => 'T恤']])->id,
            'is_active' => true,
        ]);
        $buat = app(BuatVarianAction::class);
        $buat->execute($this->kaos, ['sku' => 'K-M', 'opsi' => ['Warna' => 'Hitam', 'Ukuran' => 'M'], 'harga_idr' => 100000, 'berat_gram' => 200, 'stok_awal' => 3]);
        $buat->execute($this->kaos, ['sku' => 'K-L', 'opsi' => ['Warna' => 'Hitam', 'Ukuran' => 'L'], 'harga_idr' => 110000, 'berat_gram' => 200, 'stok_awal' => 0]);
    }

    public function test_beranda_default_english_dan_usd(): void
    {
        $this->get('/')->assertOk()->assertSee('lang="en"', false);
        $this->get(route('produk.index'))
            ->assertOk()
            ->assertSee('Black T-Shirt')
            ->assertSee('from $6.00');
    }

    public function test_ganti_bahasa_dan_mata_uang(): void
    {
        $this->post('/preferensi', ['locale' => 'id', 'currency' => 'IDR'])->assertRedirect();

        $this->get('/produk')
            ->assertOk()
            ->assertSee('Kaos Hitam')
            ->assertSee('mulai Rp100.000');

        $this->post('/preferensi', ['locale' => 'zh_TW', 'currency' => 'TWD']);
        // 100.000 x 0,002 x 1,02 = 204
        $this->get('/produk')->assertSee('黑色T恤')->assertSee('NT$204');
    }

    public function test_preferensi_tersimpan_ke_akun_yang_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/preferensi', ['locale' => 'zh_TW', 'currency' => 'TWD']);

        $this->assertSame('zh_TW', $user->fresh()->bahasa_preferensi);
        $this->assertSame('TWD', $user->fresh()->mata_uang_preferensi);
    }

    public function test_nilai_preferensi_tidak_valid_ditolak(): void
    {
        $this->post('/preferensi', ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_detail_produk_dengan_varian_dan_stok(): void
    {
        $this->get(route('produk.show', $this->kaos))
            ->assertOk()
            ->assertSee('Black T-Shirt')
            ->assertSee('Soft cotton');

        $res = $this->get(route('produk.show', $this->kaos));
        $res->assertSee('Color')->assertSee('Size')->assertSee('"sku":"K-M"', false)->assertSee('"stok":0', false);
    }

    public function test_foto_per_warna(): void
    {
        app(BuatVarianAction::class)->execute($this->kaos, ['sku' => 'K-PUTIH-M', 'opsi' => ['Warna' => 'Putih', 'Ukuran' => 'M'], 'harga_idr' => 100000, 'berat_gram' => 200, 'stok_awal' => 2]);
        $this->kaos->images()->create(['path' => 'produk/putih.webp', 'warna' => 'Putih', 'urutan' => 1]);
        $this->kaos->images()->create(['path' => 'produk/umum.webp', 'warna' => null, 'urutan' => 2]);
        $this->kaos->images()->create(['path' => 'produk/hitam.webp', 'warna' => 'Hitam', 'urutan' => 3]);
        $kaos = $this->kaos->fresh(['images', 'variants']);

        $this->assertSame(['Hitam', 'Putih'], $kaos->daftarWarna());
        $this->assertSame('produk/putih.webp', $kaos->fotoUntuk('Putih')->path);
        $this->assertSame('produk/putih.webp', $kaos->fotoUntuk(null)->path); // tanpa warna: foto pertama
        $this->assertSame('produk/putih.webp', $kaos->fotoUntuk('Merah')->path);

        // Varian awal (Hitam, ada stok) -> foto hitam tampil paling depan.
        $html = $this->get(route('produk.show', $this->kaos))->assertOk()
            ->assertSee('data-opsi-warna="Warna"', false)->getContent();
        $this->assertLessThan(strpos($html, 'data-warna="Putih"'), strpos($html, 'data-warna="Hitam"'));
        $this->assertStringContainsString('hitam.webp" alt="Black T-Shirt"', $html);

        // Kartu katalog: lebih dari 1 foto -> carousel; halaman produk: slide + thumbnail per foto.
        $this->get(route('produk.index'))->assertSee('data-geser data-otomatis', false);
        $this->assertSame(3, substr_count($html, 'class="geser-slide"'));
        $this->assertStringContainsString('data-thumb="2"', $html);

        // Keranjang memakai foto sesuai warna varian.
        $this->post(route('keranjang.tambah'), ['product_id' => $this->kaos->id, 'opsi' => ['Warna' => 'Putih', 'Ukuran' => 'M'], 'qty' => 1]);
        $this->get(route('keranjang'))->assertSee('putih-thumb.webp', false)->assertDontSee('hitam-thumb.webp', false);
    }

    public function test_bahasa_kosong_memakai_english(): void
    {
        $this->withSession(['locale' => 'zh_TW'])
            ->get(route('produk.show', $this->kaos))
            ->assertSee('Soft cotton');
    }

    public function test_produk_nonaktif_tidak_tampil(): void
    {
        $this->kaos->update(['is_active' => false]);

        $this->get(route('produk.show', $this->kaos))->assertNotFound();
        $this->get('/produk')->assertDontSee('Black T-Shirt');
    }

    public function test_filter_kategori_cari_dan_urut(): void
    {
        $celana = Product::create(['nama_terjemahan' => ['en' => 'Chino Pants', 'id' => 'Celana Chino'], 'category_id' => \App\Models\Category::create(['nama_terjemahan' => ['id' => 'Celana', 'en' => 'Pants']])->id, 'is_active' => true]);
        app(BuatVarianAction::class)->execute($celana, ['sku' => 'C-30', 'opsi' => ['Ukuran' => '30'], 'harga_idr' => 300000, 'berat_gram' => 400, 'stok_awal' => 5]);

        // Nama kategori tampil sesuai bahasa; slug tak dikenal diabaikan.
        $this->get('/produk?kategori=tidak-ada')->assertOk()->assertSee('Chino Pants')->assertSee('Black T-Shirt');
        $this->get('/produk?kategori=pants')->assertSee('<h1 class="judul-halaman">Pants</h1>', false);
        $this->get('/produk?kategori=pants')->assertSee('Chino Pants')->assertDontSee('Black T-Shirt');
        $this->get('/produk?q=chino')->assertSee('Chino Pants')->assertDontSee('Black T-Shirt');
        $this->get('/produk?urut=termahal')->assertSeeInOrder(['Chino Pants', 'Black T-Shirt']);
        $this->get('/produk?urut=termurah')->assertSeeInOrder(['Black T-Shirt', 'Chino Pants']);
        $this->get('/produk?q=tidakada')->assertSee('No products match your search.');
    }

    public function test_halaman_faq(): void
    {
        Faq::create(['pertanyaan_terjemahan' => ['en' => 'How long?', 'id' => 'Berapa lama?'], 'jawaban_terjemahan' => ['en' => '3 days', 'id' => '3 hari'], 'urutan' => 1, 'is_active' => true]);

        $this->get('/faq')->assertOk()->assertSee('How long?');
        $this->withSession(['locale' => 'id'])->get('/faq')->assertSee('Berapa lama?');
    }

    public function test_kurs_jatuh_ke_idr_kalau_belum_ada(): void
    {
        ExchangeRate::query()->delete();
        $kurs = new Kurs;

        $this->assertSame('IDR', $kurs->mataUangEfektif('USD'));
        $this->assertSame('Rp100.000', $kurs->format(100000, 'USD'));
    }
}
