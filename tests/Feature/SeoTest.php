<?php

namespace Tests\Feature;

use App\Actions\Katalog\BuatVarianAction;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Support\GambarOg;
use App\Support\ProductImageStorage;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private Product $kaos;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(StockLocationSeeder::class);
        ExchangeRate::create(['mata_uang_asal' => 'IDR', 'mata_uang_tujuan' => 'USD', 'rate' => 0.00006, 'margin_persen' => 0, 'sumber' => 'manual', 'berlaku_dari' => now()->subDay()]);

        $this->kaos = Product::create([
            'nama_terjemahan' => ['en' => 'Black T-Shirt </script>', 'id' => 'Kaos Hitam'],
            'deskripsi_terjemahan' => ['en' => 'Soft cotton tee.'],
            'category_id' => \App\Models\Category::firstOrCreate(['slug' => 't-shirts'], ['nama_terjemahan' => ['id' => 'Kaos', 'en' => 'T-shirts', 'zh_TW' => 'T恤']])->id,
            'is_active' => true,
        ]);
        app(BuatVarianAction::class)->execute($this->kaos, ['sku' => 'K-M', 'opsi' => ['Ukuran' => 'M'], 'harga_idr' => 100000, 'berat_gram' => 200, 'stok_awal' => 3]);
        app(BuatVarianAction::class)->execute($this->kaos, ['sku' => 'K-L', 'opsi' => ['Ukuran' => 'L'], 'harga_idr' => 120000, 'berat_gram' => 200, 'stok_awal' => 0]);
        $this->kaos->images()->create([
            'path' => app(ProductImageStorage::class)->store(UploadedFile::fake()->image('a.jpg', 900, 900)),
            'urutan' => 1,
        ]);
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
    }

    public function test_slug_otomatis_unik_dan_tautan_lama_dialihkan(): void
    {
        $this->assertSame('black-t-shirt-script', $this->kaos->slug);

        $kembar = Product::create(['nama_terjemahan' => ['en' => 'Black T-Shirt </script>', 'id' => 'x'], 'is_active' => true]);
        $this->assertSame('black-t-shirt-script-2', $kembar->slug);

        // Ganti nama tidak mengubah slug (tautan yang sudah dibagikan tetap jalan).
        $this->kaos->update(['nama_terjemahan' => ['en' => 'Renamed', 'id' => 'y']]);
        $this->assertSame('black-t-shirt-script', $this->kaos->fresh()->slug);

        $this->get('/produk/black-t-shirt-script')->assertOk();
        $this->get('/produk/'.$this->kaos->id)->assertRedirect('/produk/black-t-shirt-script')->assertStatus(301);
        $this->get('/produk/tidak-ada')->assertNotFound();
    }

    public function test_tag_preview_dan_data_produk(): void
    {
        $html = $this->get(route('produk.show', $this->kaos))->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:type" content="product">', $html);
        $this->assertStringContainsString('<meta name="description" content="Soft cotton tee.">', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="[^"]+/og/[0-9a-f-]+\.jpg">#', $html);
        $this->assertStringContainsString('summary_large_image', $html);

        // Gambar preview JPEG 1200x630 benar-benar dibuat.
        $foto = $this->kaos->images()->first();
        [$w, $h, $tipe] = getimagesizefromstring(Storage::disk('public')->get(GambarOg::pathFoto($foto)));
        $this->assertSame([1200, 630, IMAGETYPE_JPEG], [$w, $h, $tipe]);

        // JSON-LD Product: rentang harga IDR, nama tidak bisa memutus tag <script>.
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
        $ld = json_decode($m[1], true);
        $this->assertSame('Product', $ld['@type']);
        $this->assertSame('Black T-Shirt </script>', $ld['name']);
        $this->assertEquals(100000, $ld['offers']['lowPrice']);
        $this->assertEquals(120000, $ld['offers']['highPrice']);
        $this->assertSame('https://schema.org/InStock', $ld['offers']['availability']);
        $this->assertStringNotContainsString('</script>"', $m[1]);

        // File preview ikut terhapus bersama fotonya.
        $foto->delete();
        Storage::disk('public')->assertMissing(GambarOg::pathFoto($foto));
    }

    public function test_dev_tidak_diindeks(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('rel="canonical"', false);
    }

    public function test_production_diindeks_kecuali_halaman_pribadi(): void
    {
        $this->production();

        $this->get(route('produk.show', $this->kaos))
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertDontSee('name="robots"', false)
            ->assertSee('<link rel="canonical" href="'.route('produk.show', $this->kaos).'">', false);

        // Urutan tidak membuat URL kanonik baru; kategori iya; hasil pencarian tidak diindeks.
        $this->get(route('produk.index', ['kategori' => 't-shirts', 'urut' => 'termurah']))
            ->assertSee('<link rel="canonical" href="'.route('produk.index', ['kategori' => 't-shirts']).'">', false);
        $this->get(route('produk.index', ['q' => 'kaos']))->assertSee('content="noindex, nofollow"', false);

        $this->get(route('keranjang'))->assertSee('content="noindex, nofollow"', false);
        $this->get(route('login'))->assertSee('content="noindex, nofollow"', false);
        $this->get('/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        // Beranda: data WebSite + gambar preview gabungan.
        $this->get(route('home'))->assertSee('"@type":"WebSite"', false)->assertSee('/og/toko-', false);
    }

    public function test_sitemap(): void
    {
        Product::create(['nama_terjemahan' => ['en' => 'Hidden', 'id' => 'x'], 'is_active' => false]);

        $res = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($res->getContent());
        $loc = collect();
        foreach ($xml->url as $u) {
            $loc->push((string) $u->loc);
        }

        $this->assertContains(route('produk.show', $this->kaos), $loc->all());
        $this->assertContains(route('produk.index', ['kategori' => 't-shirts']), $loc->all());
        $this->assertContains(route('faq'), $loc->all());
        $this->assertFalse($loc->contains(fn ($l) => str_contains($l, 'hidden')));
        $this->assertStringContainsString('<image:loc>', $res->getContent());
    }
}
