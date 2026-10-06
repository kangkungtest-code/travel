<?php

namespace Tests\Feature\Concerns;

use App\Actions\Katalog\BuatVarianAction;
use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\StockLocationSeeder;

/** Data kecil yang dipakai bersama oleh test keranjang & checkout. */
trait TokoFixture
{
    protected Product $kaos;

    protected ProductVariant $kaosM;

    protected ProductVariant $kaosL;

    protected function siapkanToko(): void
    {
        $this->seed(StockLocationSeeder::class);

        $this->kaos = Product::create([
            'nama_terjemahan' => ['en' => 'Black T-Shirt', 'id' => 'Kaos Hitam'],
            'category_id' => \App\Models\Category::firstOrCreate(['slug' => 't-shirts'], ['nama_terjemahan' => ['id' => 'Kaos', 'en' => 'T-shirts', 'zh_TW' => 'T恤']])->id,
            'is_active' => true,
        ]);
        $buat = app(BuatVarianAction::class);
        $this->kaosM = $buat->execute($this->kaos, ['sku' => 'K-M', 'opsi' => ['Warna' => 'Hitam', 'Ukuran' => 'M'], 'harga_idr' => 100000, 'berat_gram' => 200, 'stok_awal' => 5]);
        $this->kaosL = $buat->execute($this->kaos, ['sku' => 'K-L', 'opsi' => ['Warna' => 'Hitam', 'Ukuran' => 'L'], 'harga_idr' => 110000, 'berat_gram' => 250, 'stok_awal' => 2]);

        $id = ShippingZone::create(['nama' => 'Indonesia', 'negara' => ['ID'], 'is_active' => true]);
        $id->rates()->createMany([
            ['berat_min_gram' => 0, 'berat_max_gram' => 1000, 'tarif_idr' => 20000],
            ['berat_min_gram' => 1001, 'berat_max_gram' => 5000, 'tarif_idr' => 40000],
        ]);
    }

    protected function pembeli(): User
    {
        return User::factory()->create();
    }

    protected function alamat(User $user, string $negara = 'ID'): Address
    {
        return $user->addresses()->create([
            'label' => 'Rumah', 'nama_penerima' => $user->nama_lengkap, 'telepon' => '08123456789',
            'negara' => $negara, 'kota' => 'Makassar', 'kode_pos' => '90111',
            'detail_alamat' => 'Jl. Contoh No. 1', 'is_default' => true,
        ]);
    }

    protected function tambahKeKeranjang(ProductVariant $v, int $qty = 1)
    {
        return $this->post(route('keranjang.tambah'), [
            'product_id' => $v->product_id,
            'opsi' => $v->opsi,
            'qty' => $qty,
        ]);
    }
}
