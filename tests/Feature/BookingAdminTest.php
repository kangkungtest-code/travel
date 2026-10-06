<?php

namespace Tests\Feature;

use App\Actions\Order\UbahStatusOrderAction;
use App\Actions\Sewa\BuatBookingAction;
use App\Actions\Sewa\OperasionalBookingAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Lokasi;
use App\Models\Order;
use App\Models\Tarif;
use App\Models\TipeKendaraan;
use App\Models\UnitKendaraan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BookingAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private TipeKendaraan $tipe;

    private UnitKendaraan $unitA;

    private UnitKendaraan $unitB;

    private Lokasi $lokasi;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['ADMIN_EMAIL'] = $_SERVER['ADMIN_EMAIL'] = 'super@toko.test';
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = 'rahasia-123';
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Filament::setCurrentPanel('admin');

        $this->owner = User::factory()->create(['nama_lengkap' => 'Pemilik']);
        $this->owner->assignRole('Owner');

        $this->lokasi = Lokasi::create(['nama_terjemahan' => ['en' => 'Kuta pool', 'id' => 'Pool Kuta'], 'kota' => 'Kuta']);
        $this->tipe = TipeKendaraan::create([
            'nama_terjemahan' => ['en' => 'Toyota Avanza', 'id' => 'Toyota Avanza'],
            'jenis' => 'mobil', 'kursi' => 7, 'transmisi' => 'otomatis', 'bbm' => 'bensin',
        ]);
        $this->tipe->tarif()->create(['mode' => Tarif::LEPAS_KUNCI, 'harga_harian' => 400000, 'harga_per_jam' => 45000, 'minimal_jam' => 12]);
        $this->tipe->tarif()->create(['mode' => Tarif::SOPIR, 'harga_harian' => 900000, 'minimal_jam' => 12]);
        $this->unitA = $this->tipe->unit()->create(['plat_nomor' => 'DK 1 AA', 'lokasi_id' => $this->lokasi->id]);
        $this->unitB = $this->tipe->unit()->create(['plat_nomor' => 'DK 2 BB', 'lokasi_id' => $this->lokasi->id]);
    }

    private function booking(string $mode = Tarif::LEPAS_KUNCI, bool $dibayar = true): Order
    {
        $mulai = CarbonImmutable::now('Asia/Makassar')->addDays(3)->setTime(9, 0);
        $order = app(BuatBookingAction::class)->execute(
            User::factory()->create(),
            $this->tipe, $this->lokasi, $mode, $mulai, $mulai->addDays(2),
            ['nama_penyewa' => 'Chen Wei', 'telepon' => '0812 3456 7890'],
            $mode === Tarif::LEPAS_KUNCI ? ['identitas' => UploadedFile::fake()->image('ktp.jpg'), 'sim' => UploadedFile::fake()->image('sim.jpg')] : [],
            'IDR',
        );
        if ($dibayar) {
            app(UbahStatusOrderAction::class)->execute($order, Order::STATUS_DIBAYAR);
        }

        return $order->fresh();
    }

    public function test_daftar_dan_detail_booking(): void
    {
        $order = $this->booking();
        $this->actingAs($this->owner, 'admin');

        $this->get(OrderResource::getUrl('index'))->assertOk()->assertSee($order->nomor)->assertSee('Toyota Avanza')->assertSee('DK 1 AA');
        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('Chen Wei')
            ->assertSee('KTP/paspor')
            ->assertSee('wa.me/6281234567890', false);
    }

    public function test_dokumen_hanya_untuk_admin_berizin(): void
    {
        $order = $this->booking();
        $url = route('admin.dokumen-sewa', [$order->bookingSewa, 'identitas']);

        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create(), 'admin')->get($url)->assertForbidden();
        $this->actingAs($this->owner, 'admin')->get($url)->assertOk();
        $this->get(route('admin.dokumen-sewa', [$order->bookingSewa, 'sim']).'x')->assertNotFound();
    }

    public function test_alur_konfirmasi_serah_terima_pengembalian_lewat_panel(): void
    {
        $order = $this->booking();
        $this->actingAs($this->owner, 'admin');
        $page = fn () => Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);

        $page()->assertActionVisible('konfirmasiBooking')->assertActionHidden('proses')
            ->callAction('konfirmasiBooking', ['unit_kendaraan_id' => $this->unitB->id])
            ->assertHasNoActionErrors();
        $order->refresh();
        $this->assertSame(Order::STATUS_DIPROSES, $order->status);
        $this->assertSame($this->unitB->id, $order->bookingSewa->unit_kendaraan_id);

        $page()->assertActionHidden('kirim')->callAction('serahTerima', ['km' => 15000, 'bbm' => 'penuh'])->assertHasNoActionErrors();
        $order->refresh();
        $this->assertSame(Order::STATUS_DIKIRIM, $order->status);
        $this->assertNull($order->resi);
        $this->assertSame(15000, $order->bookingSewa->serah_terima['km']);

        $page()->callAction('pengembalian', ['km' => 15420, 'bbm' => '3/4', 'denda_telat' => 0, 'biaya_lain' => 50000])->assertHasNoActionErrors();
        $order->refresh();
        $this->assertSame(Order::STATUS_SELESAI, $order->status);
        $this->assertSame(420, $order->bookingSewa->pengembalian['jarak_km']);
        $this->assertEquals(50000, $order->bookingSewa->pengembalian['biaya_lain']);
    }

    public function test_ganti_unit_ke_unit_yang_bentrok_ditolak(): void
    {
        $pertama = $this->booking();          // dapat DK 1 AA
        $kedua = $this->booking();            // dapat DK 2 BB
        $this->assertSame($this->unitB->id, $kedua->bookingSewa->unit_kendaraan_id);

        $this->expectException(\App\Exceptions\TokoException::class);
        app(OperasionalBookingAction::class)->gantiUnit($kedua, $this->unitA->id);
    }

    public function test_sewa_dengan_sopir_wajib_isi_sopir(): void
    {
        $order = $this->booking(Tarif::SOPIR);

        try {
            app(OperasionalBookingAction::class)->konfirmasi($order, $this->owner, []);
            $this->fail('Seharusnya ditolak tanpa sopir');
        } catch (\App\Exceptions\TokoException) {
        }
        $this->assertSame(Order::STATUS_DIBAYAR, $order->fresh()->status);

        app(OperasionalBookingAction::class)->konfirmasi($order, $this->owner, ['sopir_nama' => 'Made', 'sopir_telepon' => '0811']);
        $this->assertSame('Made', $order->fresh()->bookingSewa->sopir['nama']);
    }

    public function test_saran_denda_keterlambatan(): void
    {
        $b = $this->booking()->bookingSewa;

        $this->assertSame(['jam' => 0, 'denda' => 0.0], OperasionalBookingAction::keterlambatan($b, $b->selesai->addMinutes(20)));
        $this->assertSame(['jam' => 3, 'denda' => 135000.0], OperasionalBookingAction::keterlambatan($b, $b->selesai->addMinutes(150)));
        // Lebih dari sehari: 1 × harian + sisa jam (maks harian).
        $this->assertSame(['jam' => 26, 'denda' => 490000.0], OperasionalBookingAction::keterlambatan($b, $b->selesai->addHours(26)));
    }

    public function test_pembeli_melihat_plat_setelah_dikonfirmasi(): void
    {
        $order = $this->booking(dibayar: false);
        $this->actingAs($order->user, 'web');
        $this->get(route('akun.pesanan.show', $order))->assertDontSee('DK 1 AA');

        app(UbahStatusOrderAction::class)->execute($order, Order::STATUS_DIBAYAR);
        app(OperasionalBookingAction::class)->konfirmasi($order->fresh(), $this->owner);
        $this->get(route('akun.pesanan.show', $order))->assertSee('DK 1 AA')->assertSee('Booking confirmed');
    }
}
