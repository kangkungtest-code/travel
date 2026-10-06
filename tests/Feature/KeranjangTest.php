<?php

namespace Tests\Feature;

use App\Models\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\TokoFixture;
use Tests\TestCase;

class KeranjangTest extends TestCase
{
    use RefreshDatabase, TokoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanToko();
    }

    public function test_tamu_bisa_menambah_ke_keranjang(): void
    {
        $this->tambahKeKeranjang($this->kaosM, 2)->assertSessionHas('ditambahkan');
        $this->tambahKeKeranjang($this->kaosM, 1);

        $this->get('/keranjang')->assertOk()->assertSee('Black T-Shirt')->assertSee('Size: M');
        $this->assertSame(3, Cart::first()->items()->first()->qty);
    }

    public function test_tidak_bisa_melebihi_stok(): void
    {
        $this->tambahKeKeranjang($this->kaosL, 3)->assertSessionHasErrors('keranjang');
        $this->tambahKeKeranjang($this->kaosL, 2)->assertSessionHasNoErrors();
        $this->tambahKeKeranjang($this->kaosL, 1)->assertSessionHasErrors('keranjang');
    }

    public function test_opsi_tidak_lengkap_ditolak(): void
    {
        $this->post(route('keranjang.tambah'), ['product_id' => $this->kaos->id, 'opsi' => ['Warna' => 'Hitam']])
            ->assertSessionHasErrors('keranjang');
    }

    public function test_ubah_dan_hapus_item(): void
    {
        $this->tambahKeKeranjang($this->kaosM, 1);
        $item = Cart::first()->items()->first();

        $this->patch(route('keranjang.ubah', $item), ['qty' => 4])->assertSessionHasNoErrors();
        $this->assertSame(4, $item->fresh()->qty);

        $this->patch(route('keranjang.ubah', $item), ['qty' => 9])->assertSessionHasErrors('keranjang');

        $this->delete(route('keranjang.hapus', $item));
        $this->assertNull($item->fresh());
    }

    public function test_tidak_bisa_mengubah_keranjang_orang_lain(): void
    {
        $this->actingAs($this->pembeli(), 'web');
        $this->tambahKeKeranjang($this->kaosM, 1);
        $itemOrangLain = Cart::first()->items()->first();

        $this->post('/keluar');
        $this->patch(route('keranjang.ubah', $itemOrangLain), ['qty' => 2])->assertNotFound();
    }

    public function test_keranjang_tamu_pindah_ke_akun_saat_masuk(): void
    {
        $user = $this->pembeli();
        $user->update(['password' => 'rahasia123']);

        $this->tambahKeKeranjang($this->kaosM, 2);
        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123']);

        $cart = $user->fresh()->cart;
        $this->assertSame(2, $cart->items()->first()->qty);
        $this->assertSame(1, Cart::count());
        $this->get('/')->assertSee('<span class="jumlah"', false);
    }
}
