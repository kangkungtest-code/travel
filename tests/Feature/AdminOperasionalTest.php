<?php

namespace Tests\Feature;

use App\Actions\Order\BuatOrderAction;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ExchangeRates\Pages\ManageExchangeRates;
use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\ReturnRequests\Pages\ViewReturnRequest;
use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Filament\Resources\StockHistories\StockHistoryResource;
use App\Filament\Resources\Stocks\StockResource;
use App\Filament\Widgets\GrafikPenjualan;
use App\Filament\Widgets\PenjualanKategori;
use App\Filament\Widgets\ProdukTerlaris;
use App\Filament\Widgets\RingkasanPenjualan;
use App\Filament\Widgets\StatusOrder;
use App\Filament\Widgets\StokMenipis;
use App\Models\ExchangeRate;
use App\Models\Faq;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\Stock;
use App\Models\User;
use App\Notifications\OrderDibuat;
use App\Notifications\OrderDikirim;
use App\Notifications\ReturDiperbarui;
use App\Support\Laporan;
use Database\Seeders\RoleAndPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\TokoFixture;
use Tests\TestCase;

class AdminOperasionalTest extends TestCase
{
    use RefreshDatabase, TokoFixture;

    private User $admin;

    private User $pembeli;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('public');
        $this->siapkanToko();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Owner');
        $this->pembeli = $this->pembeli();

        Filament::setCurrentPanel('admin');
    }

    private function buatOrder(int $qty = 2): Order
    {
        $alamat = $this->alamat($this->pembeli);
        $this->pembeli->cart()->create()->items()->create(['variant_id' => $this->kaosM->id, 'qty' => $qty]);

        return app(BuatOrderAction::class)->execute($this->pembeli, $alamat, 'IDR');
    }

    private function stokM(): Stock
    {
        return Stock::where('variant_id', $this->kaosM->id)->firstOrFail();
    }

    public function test_alur_order_dari_admin_sampai_selesai(): void
    {
        $order = $this->buatOrder(2);
        Notification::assertSentTo($this->pembeli, OrderDibuat::class);
        $this->actingAs($this->admin, 'admin');

        $this->get(OrderResource::getUrl('index'))->assertOk()->assertSee($order->nomor);
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk()->assertSee('Konfirmasi pembayaran manual');

        $lw = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);

        $lw->callAction('konfirmasiBayar', ['catatan' => 'Transfer BCA dicek']);
        $order->refresh();
        $this->assertSame(Order::STATUS_DIBAYAR, $order->status);
        $this->assertNotNull($order->dibayar_pada);
        // Stok fisik berkurang, reservasi lepas.
        $this->assertSame(3, $this->stokM()->jumlah);
        $this->assertSame(0, $this->stokM()->jumlah_reserved);

        // Pembeli melihat pelacak progres setelah dibayar.
        $this->actingAs($this->pembeli, 'web')->get(route('akun.pesanan.show', $order))
            ->assertSee('Payment received')->assertSee('aria-current="step"', false);
        $this->actingAs($this->admin, 'admin');

        $lw->callAction('proses');
        $this->assertSame(Order::STATUS_DIPROSES, $order->fresh()->status);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('kirim', ['resi' => ''])->assertHasActionErrors(['resi' => 'required']);
        $lw = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);
        $lw->callAction('kirim', ['resi' => 'JNE123456']);
        $this->assertSame(Order::STATUS_DIKIRIM, $order->fresh()->status);
        $this->assertSame('JNE123456', $order->fresh()->resi);
        Notification::assertSentTo($this->pembeli, OrderDikirim::class);

        $lw->callAction('selesai');
        $order->refresh();
        $this->assertSame(Order::STATUS_SELESAI, $order->status);
        $this->assertNotNull($order->selesai_pada);

        $this->assertSame(
            ['menunggu_pembayaran', 'dibayar', 'diproses', 'dikirim', 'selesai'],
            $order->statusHistories()->pluck('ke')->all(),
        );

        // Pembeli melihat riwayat & resi.
        $this->actingAs($this->pembeli, 'web')->get(route('akun.pesanan.show', $order))
            ->assertSee('JNE123456')->assertSee('Order completed');
    }

    public function test_admin_membatalkan_order_belum_dibayar(): void
    {
        $order = $this->buatOrder(2);
        $this->actingAs($this->admin, 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('batalkan')
            ->assertActionHidden('proses')
            ->callAction('batalkan', ['catatan' => 'Pembeli minta batal lewat WA']);

        $this->assertSame(Order::STATUS_DIBATALKAN, $order->fresh()->status);
        $this->assertSame(0, $this->stokM()->jumlah_reserved);
        $this->assertSame('Pembeli minta batal lewat WA', $order->statusHistories()->where('ke', Order::STATUS_DIBATALKAN)->first()->catatan);
    }

    public function test_retur_dari_pembeli_sampai_selesai(): void
    {
        $order = $this->buatOrder(1);
        $order->forceFill(['status' => Order::STATUS_SELESAI, 'selesai_pada' => now()])->save();

        // Pembeli mengajukan retur.
        $this->actingAs($this->pembeli, 'web')
            ->get(route('akun.pesanan.show', $order))->assertSee('Item arrived damaged or wrong?');
        $this->post(route('akun.pesanan.retur', $order), ['alasan' => 'pendek'])->assertSessionHasErrors(['alasan', 'foto']);
        $this->post(route('akun.pesanan.retur', $order), [
            'alasan' => 'Jahitan lengan robek saat dibuka.',
            'foto' => UploadedFile::fake()->image('robek.jpg', 1200, 900),
        ])->assertSessionHas('status');

        $retur = ReturnRequest::firstOrFail();
        $this->assertSame(ReturnRequest::STATUS_DIAJUKAN, $retur->status);
        Storage::disk('public')->assertExists($retur->foto_bukti);
        $this->assertStringStartsWith('retur/', $retur->foto_bukti);

        // Tidak bisa mengajukan dua kali.
        $this->post(route('akun.pesanan.retur', $order), ['alasan' => 'Jahitan lengan robek lagi.', 'foto' => UploadedFile::fake()->image('a.jpg')])
            ->assertSessionHasErrors('order');

        // Admin menyetujui.
        $this->actingAs($this->admin, 'admin');
        $this->get(ReturnRequestResource::getUrl('index'))->assertOk()->assertSee($order->nomor);
        Livewire::test(ViewReturnRequest::class, ['record' => $retur->getRouteKey()])
            ->callAction('setujui', ['catatan' => 'Maaf atas ketidaknyamanannya']);
        $this->assertSame(ReturnRequest::STATUS_DISETUJUI, $retur->fresh()->status);
        Notification::assertSentTo($this->pembeli, ReturDiperbarui::class);

        // Pembeli mengisi resi kembali.
        $this->actingAs($this->pembeli, 'web')
            ->put(route('akun.retur.resi', $retur), ['resi_kembali' => 'JNT999'])->assertSessionHas('status');
        $this->assertSame('JNT999', $retur->fresh()->resi_kembali);

        // Admin menerima barang dan menyelesaikan.
        $this->actingAs($this->admin, 'admin');
        Livewire::test(ViewReturnRequest::class, ['record' => $retur->getRouteKey()])
            ->callAction('selesaikan', ['penyelesaian' => 'ganti_barang']);
        $this->assertSame(ReturnRequest::STATUS_SELESAI, $retur->fresh()->status);
        $this->assertSame('ganti_barang', $retur->fresh()->penyelesaian);
    }

    public function test_retur_ditolak_wajib_alasan_dan_batas_waktu(): void
    {
        $order = $this->buatOrder(1);
        $order->forceFill(['status' => Order::STATUS_SELESAI, 'selesai_pada' => now()->subDays(8)])->save();

        $this->actingAs($this->pembeli, 'web')
            ->get(route('akun.pesanan.show', $order))->assertDontSee('Item arrived damaged or wrong?');

        $order->forceFill(['selesai_pada' => now()])->save();
        $retur = $order->returnRequests()->create(['alasan' => 'Salah ukuran dikirim', 'status' => ReturnRequest::STATUS_DIAJUKAN]);

        $this->actingAs($this->admin, 'admin');
        Livewire::test(ViewReturnRequest::class, ['record' => $retur->getRouteKey()])
            ->callAction('tolak', ['catatan' => ''])->assertHasActionErrors(['catatan' => 'required']);
        Livewire::test(ViewReturnRequest::class, ['record' => $retur->getRouteKey()])
            ->callAction('tolak', ['catatan' => 'Barang sudah dipakai']);
        $this->assertSame(ReturnRequest::STATUS_DITOLAK, $retur->fresh()->status);
    }

    public function test_halaman_stok_riwayat_dan_dashboard(): void
    {
        $order = $this->buatOrder(2);
        $this->actingAs($this->admin, 'admin');
        app(\App\Actions\Order\UbahStatusOrderAction::class)->execute($order, Order::STATUS_DIBAYAR, $this->admin);

        $this->get(StockResource::getUrl())->assertOk()->assertSee('K-M');
        $this->get(StockHistoryResource::getUrl())->assertOk()->assertSee('Terjual (order dibayar)');
        $this->get('/admin')->assertOk();

        foreach ([RingkasanPenjualan::class, GrafikPenjualan::class, ProdukTerlaris::class, PenjualanKategori::class, StatusOrder::class, StokMenipis::class] as $w) {
            Livewire::test($w, ['pageFilters' => []])->assertOk();
        }

        $laporan = new Laporan;
        $this->assertEquals(220000, $laporan->ringkasan()['penjualan']); // 2 x 100rb + ongkir 20rb
        $this->assertSame(['Kaos Hitam' => 2], $laporan->produkTerlaris()->all());
        $this->assertSame(30, $laporan->penjualanHarian()->count());
        $this->assertEquals(200000, $laporan->penjualanPerKategori()['Kaos']);
    }

    public function test_faq_dan_kurs_dari_admin(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(ManageFaqs::class)
            ->callAction('create', [
                'pertanyaan_terjemahan' => ['en' => 'Do you ship to Japan?', 'id' => 'Kirim ke Jepang?', 'zh_TW' => null],
                'jawaban_terjemahan' => ['en' => 'Not yet.', 'id' => 'Belum.', 'zh_TW' => null],
                'urutan' => 1,
                'is_active' => true,
            ])->assertHasNoActionErrors();
        $this->assertSame('Kirim ke Jepang?', Faq::firstOrFail()->getTranslation('pertanyaan_terjemahan', 'id'));

        Livewire::test(ManageExchangeRates::class)
            ->callAction('create', ['mata_uang_tujuan' => 'USD', 'per_unit' => 16000, 'margin_persen' => 2, 'berlaku_dari' => now()->subMinute()])
            ->assertHasNoActionErrors();
        $kurs = ExchangeRate::firstOrFail();
        $this->assertEqualsWithDelta(1 / 16000, (float) $kurs->rate, 1e-10);
        $this->assertSame('IDR', $kurs->mata_uang_asal);
    }

    public function test_customer_tidak_bisa_membuka_menu_admin(): void
    {
        $this->actingAs($this->pembeli, 'admin');

        foreach ([OrderResource::getUrl('index'), StockResource::getUrl(), ReturnRequestResource::getUrl('index')] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}
