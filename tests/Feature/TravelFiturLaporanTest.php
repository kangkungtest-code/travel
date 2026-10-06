<?php

namespace Tests\Feature;

use App\Filament\Widgets\JadwalArmada;
use App\Filament\Widgets\PendapatanKendaraan;
use App\Filament\Widgets\RingkasanSewa;
use App\Filament\Widgets\UtilisasiArmada;
use App\Models\BookingSewa;
use App\Models\Lokasi;
use App\Models\Order;
use App\Models\Pengaturan;
use App\Models\Tarif;
use App\Models\TipeKendaraan;
use App\Models\User;
use App\Support\Fitur;
use App\Support\Laporan;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TravelFiturLaporanTest extends TestCase
{
    use RefreshDatabase;

    private Lokasi $lokasi;

    private TipeKendaraan $mobil;

    private TipeKendaraan $motor;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['ADMIN_EMAIL'] = $_SERVER['ADMIN_EMAIL'] = 'super@toko.test';
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = 'rahasia-123';
        $this->seed(DatabaseSeeder::class);

        $this->lokasi = Lokasi::create(['nama_terjemahan' => ['en' => 'Kuta pool', 'id' => 'Pool Kuta'], 'kota' => 'Kuta']);
        $this->mobil = TipeKendaraan::create(['nama_terjemahan' => ['en' => 'Toyota Avanza', 'id' => 'Toyota Avanza'], 'jenis' => 'mobil', 'kursi' => 7, 'transmisi' => 'otomatis', 'bbm' => 'bensin']);
        $this->mobil->tarif()->create(['mode' => Tarif::LEPAS_KUNCI, 'harga_harian' => 400000]);
        $this->mobil->tarif()->create(['mode' => Tarif::SOPIR, 'harga_harian' => 900000]);
        $this->mobil->unit()->create(['plat_nomor' => 'DK 1 AA', 'lokasi_id' => $this->lokasi->id]);
        $this->mobil->unit()->create(['plat_nomor' => 'DK 2 AA', 'lokasi_id' => $this->lokasi->id]);

        $this->motor = TipeKendaraan::create(['nama_terjemahan' => ['en' => 'Honda Scoopy', 'id' => 'Honda Scoopy'], 'jenis' => 'motor', 'kursi' => 2, 'transmisi' => 'otomatis', 'bbm' => 'bensin']);
        $this->motor->tarif()->create(['mode' => Tarif::LEPAS_KUNCI, 'harga_harian' => 90000]);
        $this->motor->unit()->create(['plat_nomor' => 'DK 3 AA', 'lokasi_id' => $this->lokasi->id]);
    }

    public function test_paket_1_hanya_sewa_mobil_lepas_kunci(): void
    {
        Fitur::terapkan(1, []);

        $this->assertSame(['lepas_kunci'], array_keys(config('travel.mode')));
        $this->assertArrayNotHasKey('motor', config('travel.jenis'));
        $this->assertNull($this->mobil->tarifUntuk(Tarif::SOPIR));
        $this->assertSame(400000.0, $this->mobil->fresh()->hargaMulai());

        $this->get(route('sewa.index'))->assertOk()->assertSee('Toyota Avanza')->assertDontSee('Honda Scoopy')->assertDontSee('With driver');
    }

    public function test_paket_2_membuka_sopir_dan_motor(): void
    {
        Fitur::terapkan(2, []);

        $this->assertSame(['lepas_kunci', 'sopir'], array_keys(config('travel.mode')));
        $this->assertArrayHasKey('motor', config('travel.jenis'));
        $this->assertArrayNotHasKey('bus', config('travel.jenis'));
        $this->get(route('sewa.index'))->assertSee('Honda Scoopy')->assertSee('With driver');
    }

    public function test_hanya_sopir_kalau_lepas_kunci_dimatikan(): void
    {
        Fitur::terapkan(1, ['mode_sopir']);

        $this->assertSame(['sopir'], array_keys(config('travel.mode')));
        $this->assertSame(900000.0, $this->mobil->fresh()->hargaMulai());
        $this->assertFalse(TipeKendaraan::query()->tampil()->whereKey($this->motor->id)->exists());
    }

    public function test_utilisasi_dan_pendapatan(): void
    {
        $tz = 'Asia/Makassar';
        $hariIni = CarbonImmutable::now($tz)->startOfDay();
        // Satu unit Avanza tersewa 48 jam di periode 10 hari terakhir.
        $order = Order::create([
            'nomor' => 'KT-UJI-1', 'user_id' => User::factory()->create()->id, 'status' => Order::STATUS_SELESAI,
            'sumber_order' => 'sewa', 'mata_uang' => 'IDR', 'kurs_terpakai' => 1, 'subtotal' => 800000, 'ongkir' => 0, 'total' => 800000,
            'subtotal_idr' => 800000, 'ongkir_idr' => 0, 'total_idr' => 800000, 'berat_gram' => 0,
            'dibayar_pada' => $hariIni->subDays(5)->utc(),
        ]);
        BookingSewa::create([
            'order_id' => $order->id, 'tipe_kendaraan_id' => $this->mobil->id, 'lokasi_id' => $this->lokasi->id, 'mode' => Tarif::LEPAS_KUNCI,
            'mulai' => $hariIni->subDays(5)->setTime(9, 0)->utc(), 'selesai' => $hariIni->subDays(3)->setTime(9, 0)->utc(),
            'nama_penyewa' => 'Uji', 'telepon' => '0812', 'rincian' => ['hari' => 2],
        ]);

        $laporan = new Laporan($hariIni->subDays(9)->toDateString(), $hariIni->toDateString());
        // Kapasitas Avanza: 2 unit × 240 jam = 480 jam; tersewa 48 jam → 10%.
        $this->assertEquals(10.0, $laporan->utilisasiArmada()['Toyota Avanza']);
        $this->assertEquals(0.0, $laporan->utilisasiArmada()['Honda Scoopy']);
        // Seluruh armada: 3 unit × 240 jam = 720 jam → 6,7%.
        $this->assertEquals(6.7, $laporan->utilisasiTotal());
        $this->assertEquals(800000, $laporan->pendapatanPerKendaraan()['Toyota Avanza']);
    }

    public function test_dashboard_travel_bisa_dibuka(): void
    {
        Filament::setCurrentPanel('admin');
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner, 'admin');

        $this->get('/admin')->assertOk();
        foreach ([RingkasanSewa::class, JadwalArmada::class, UtilisasiArmada::class, PendapatanKendaraan::class] as $w) {
            Livewire::test($w, ['pageFilters' => []])->assertOk();
        }
    }
}
