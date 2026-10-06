<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Stock;
use App\Models\StockHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Concerns\TokoFixture;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase, TokoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanToko();
    }

    private function stok($variant): Stock
    {
        return Stock::where('variant_id', $variant->id)->firstOrFail();
    }

    public function test_alur_checkout_lengkap(): void
    {
        $user = $this->pembeli();
        $alamat = $this->alamat($user);
        $this->actingAs($user, 'web')->withSession(['currency' => 'IDR']);

        $this->tambahKeKeranjang($this->kaosM, 2);
        $this->tambahKeKeranjang($this->kaosL, 1);

        // 2x200 + 250 = 650 g -> tarif 0-1000 g = Rp20.000
        $this->get('/checkout')->assertOk()->assertSee('Rp20.000')->assertSee('Rp330.000');

        $res = $this->post('/checkout', ['address_id' => $alamat->id]);
        $order = Order::firstOrFail();
        $res->assertRedirect(route('akun.pesanan.show', $order));

        $this->assertSame(Order::STATUS_MENUNGGU_PEMBAYARAN, $order->status);
        $this->assertMatchesRegularExpression('/^'.preg_quote(config('toko.order.prefix_nomor'), '/').'-\d{6}-[A-Z0-9]{5}$/', $order->nomor);
        $this->assertSame('IDR', $order->mata_uang);
        $this->assertEquals(310000, $order->subtotal);
        $this->assertEquals(20000, $order->ongkir);
        $this->assertEquals(330000, $order->total);
        $this->assertEquals(330000, $order->total_idr);
        $this->assertSame(650, $order->berat_gram);
        $this->assertSame('Makassar', $order->alamat_snapshot['kota']);
        $this->assertTrue($order->kadaluarsa_pada->between(now()->addHours(23), now()->addHours(25)));
        $this->assertCount(2, $order->items);

        // Stok di-reserve, fisik belum berkurang.
        $this->assertSame(5, $this->stok($this->kaosM)->jumlah);
        $this->assertSame(2, $this->stok($this->kaosM)->jumlah_reserved);
        $this->assertSame(3, $this->kaosM->fresh()->stokTersedia());
        $this->assertSame(2, StockHistory::where('order_id', $order->id)->where('alasan', 'reserve')->count());

        // Keranjang kosong, halaman order tampil.
        $this->assertSame(0, $user->cart->items()->count());
        $this->get(route('akun.pesanan.show', $order))->assertOk()->assertSee($order->nomor)->assertSee('Awaiting payment');
    }

    public function test_order_dalam_usd_menyimpan_snapshot_kurs(): void
    {
        ExchangeRate::create(['mata_uang_asal' => 'IDR', 'mata_uang_tujuan' => 'USD', 'rate' => 0.00006, 'margin_persen' => 0, 'sumber' => 'manual', 'berlaku_dari' => now()->subDay()]);
        $user = $this->pembeli();
        $alamat = $this->alamat($user);
        $this->actingAs($user, 'web')->withSession(['currency' => 'USD']);

        $this->tambahKeKeranjang($this->kaosM, 1);
        $this->get('/checkout')->assertSee('$7.20');
        $this->post('/checkout', ['address_id' => $alamat->id]);

        $order = Order::firstOrFail();
        $this->assertSame('USD', $order->mata_uang);
        $this->assertEquals(0.00006, (float) $order->kurs_terpakai);
        $this->assertEquals(6.00, $order->subtotal);
        $this->assertEquals(1.20, $order->ongkir);
        $this->assertEquals(7.20, $order->total);
        $this->assertEquals(120000, $order->total_idr);

        // Kurs berubah, order lama tetap.
        ExchangeRate::query()->update(['rate' => 0.0001]);
        $this->get(route('akun.pesanan.show', $order))->assertSee('$7.20');
    }

    public function test_stok_habis_saat_checkout_ditolak_tanpa_efek(): void
    {
        $user = $this->pembeli();
        $alamat = $this->alamat($user);
        $this->actingAs($user, 'web');
        $this->tambahKeKeranjang($this->kaosL, 2);

        // Pembeli lain membeli duluan.
        $this->stok($this->kaosL)->update(['jumlah_reserved' => 1]);

        $this->post('/checkout', ['address_id' => $alamat->id])->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $this->stok($this->kaosL)->jumlah_reserved);
        $this->assertSame(1, $user->cart->items()->count());
    }

    public function test_negara_tanpa_tarif_ditolak(): void
    {
        $user = $this->pembeli();
        $alamat = $this->alamat($user, 'JP');
        $this->actingAs($user, 'web');
        $this->tambahKeKeranjang($this->kaosM, 1);

        $this->get('/checkout')->assertSee('We can&#039;t ship to this address yet.', false);
        $this->post('/checkout', ['address_id' => $alamat->id])->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::count());
    }

    public function test_tidak_bisa_memakai_alamat_orang_lain(): void
    {
        $lain = $this->alamat($this->pembeli());
        $this->actingAs($this->pembeli(), 'web');
        $this->tambahKeKeranjang($this->kaosM, 1);

        $this->post('/checkout', ['address_id' => $lain->id])->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::count());
    }

    public function test_pembeli_membatalkan_order_dan_stok_kembali(): void
    {
        $user = $this->pembeli();
        $alamat = $this->alamat($user);
        $this->actingAs($user, 'web');
        $this->tambahKeKeranjang($this->kaosM, 3);
        $this->post('/checkout', ['address_id' => $alamat->id]);
        $order = Order::firstOrFail();

        $this->post(route('akun.pesanan.batal', $order))->assertSessionHas('status');

        $this->assertSame(Order::STATUS_DIBATALKAN, $order->fresh()->status);
        $this->assertSame(0, $this->stok($this->kaosM)->jumlah_reserved);
        $this->assertSame(1, StockHistory::where('order_id', $order->id)->where('alasan', 'lepas')->count());

        // Tidak bisa dibatalkan dua kali.
        $this->post(route('akun.pesanan.batal', $order))->assertSessionHasErrors('order');
        $this->assertSame(1, StockHistory::where('order_id', $order->id)->where('alasan', 'lepas')->count());
    }

    public function test_order_orang_lain_tidak_bisa_dilihat(): void
    {
        $pemilik = $this->pembeli();
        $alamat = $this->alamat($pemilik);
        $this->actingAs($pemilik, 'web');
        $this->tambahKeKeranjang($this->kaosM, 1);
        $this->post('/checkout', ['address_id' => $alamat->id]);
        $order = Order::firstOrFail();

        $this->actingAs($this->pembeli(), 'web');
        $this->get(route('akun.pesanan.show', $order))->assertNotFound();
        $this->post(route('akun.pesanan.batal', $order))->assertNotFound();
    }

    public function test_scheduler_mengkadaluarsakan_order_dan_melepas_stok(): void
    {
        $user = $this->pembeli();
        $alamat = $this->alamat($user);
        $this->actingAs($user, 'web');
        $this->tambahKeKeranjang($this->kaosM, 2);
        $this->post('/checkout', ['address_id' => $alamat->id]);
        $order = Order::firstOrFail();

        Artisan::call('toko:kadaluarsakan-order');
        $this->assertSame(Order::STATUS_MENUNGGU_PEMBAYARAN, $order->fresh()->status);

        $this->travel(25)->hours();
        Artisan::call('toko:kadaluarsakan-order');

        $this->assertSame(Order::STATUS_KADALUARSA, $order->fresh()->status);
        $this->assertSame(0, $this->stok($this->kaosM)->jumlah_reserved);
        $this->assertSame(5, $this->kaosM->fresh()->stokTersedia());
    }

    public function test_state_machine_tidak_bisa_loncat(): void
    {
        $order = new Order(['status' => Order::STATUS_MENUNGGU_PEMBAYARAN]);
        $this->assertTrue($order->bisaPindahKe(Order::STATUS_DIBAYAR));
        $this->assertFalse($order->bisaPindahKe(Order::STATUS_DIKIRIM));

        $order->status = Order::STATUS_KADALUARSA;
        $this->assertFalse($order->bisaPindahKe(Order::STATUS_DIBAYAR));
    }

    public function test_alamat_crud_dan_utama(): void
    {
        $user = $this->pembeli();
        $this->actingAs($user, 'web');
        $data = ['label' => 'Rumah', 'nama_penerima' => 'Budi', 'telepon' => '0812 3456 789', 'negara' => 'ID', 'kota' => 'Makassar', 'kode_pos' => '90111', 'detail_alamat' => 'Jl. A 1'];

        $this->post(route('akun.alamat.store'), $data)->assertRedirect(route('akun'));
        $this->post(route('akun.alamat.store'), ['label' => 'Kantor', 'is_default' => '1'] + $data);
        $this->post(route('akun.alamat.store'), ['negara' => 'FR'] + $data)->assertSessionHasErrors('negara');

        $rumah = Address::where('label', 'Rumah')->first();
        $kantor = Address::where('label', 'Kantor')->first();
        $this->assertFalse($rumah->fresh()->is_default);
        $this->assertTrue($kantor->is_default);

        // Alamat yang sudah dipakai order tetap bisa dihapus; order memegang salinannya.
        $this->tambahKeKeranjang($this->kaosM, 1);
        $this->post('/checkout', ['address_id' => $kantor->id]);
        $this->delete(route('akun.alamat.destroy', $kantor))->assertSessionHas('status');
        $this->assertTrue($rumah->fresh()->is_default);
        $this->assertSame('Kantor', Order::first()->alamat_snapshot['label']);
    }
}
