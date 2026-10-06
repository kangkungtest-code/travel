<?php

namespace Tests\Feature;

use App\Models\BookingSewa;
use App\Models\Lokasi;
use App\Models\Order;
use App\Models\Tarif;
use App\Models\TipeKendaraan;
use App\Models\UnitKendaraan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SewaTest extends TestCase
{
    use RefreshDatabase;

    private Lokasi $lokasi;

    private TipeKendaraan $avanza;

    private UnitKendaraan $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('local');

        $this->lokasi = Lokasi::create(['nama_terjemahan' => ['en' => 'Kuta pool', 'id' => 'Pool Kuta'], 'kota' => 'Kuta']);
        $this->avanza = TipeKendaraan::create([
            'nama_terjemahan' => ['en' => 'Toyota Avanza', 'id' => 'Toyota Avanza'],
            'jenis' => 'mobil', 'kursi' => 7, 'transmisi' => 'otomatis', 'bbm' => 'bensin',
        ]);
        $this->avanza->tarif()->create(['mode' => Tarif::LEPAS_KUNCI, 'harga_harian' => 400000, 'harga_12jam' => 275000, 'minimal_jam' => 12]);
        $this->avanza->tarif()->create(['mode' => Tarif::SOPIR, 'harga_harian' => 900000, 'minimal_jam' => 12, 'termasuk_bbm' => true]);
        $this->unit = $this->avanza->unit()->create(['plat_nomor' => 'DK 1 AA', 'lokasi_id' => $this->lokasi->id]);
    }

    /** Parameter pencarian: mulai lusa 09.00 WITA, selama $jam jam. */
    private function cari(int $jam = 48, string $mode = Tarif::LEPAS_KUNCI, int $hariLagi = 2): array
    {
        $mulai = CarbonImmutable::now('Asia/Makassar')->addDays($hariLagi)->setTime(9, 0);

        return [
            'lokasi' => $this->lokasi->id,
            'mulai' => $mulai->format('Y-m-d\TH:i'),
            'selesai' => $mulai->addHours($jam)->format('Y-m-d\TH:i'),
            'mode' => $mode,
        ];
    }

    private function penyewa(): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'nama_lengkap' => 'Chen Wei']);
    }

    private function dokumen(): array
    {
        return [
            'identitas' => UploadedFile::fake()->image('ktp.jpg'),
            'sim' => UploadedFile::fake()->create('sim.pdf', 200, 'application/pdf'),
        ];
    }

    private function pesan(User $user, array $cari, array $isian = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user, 'web')->post(
            route('sewa.pesan', ['kendaraan' => $this->avanza] + $cari),
            $isian + ['nama_penyewa' => 'Chen Wei', 'telepon' => '+886 912 345 678'] + ($cari['mode'] === Tarif::LEPAS_KUNCI ? $this->dokumen() : []),
        );
    }

    public function test_beranda_menampilkan_form_dan_kendaraan(): void
    {
        $this->get('/')->assertOk()->assertSee('Toyota Avanza')->assertSee('name="mulai"', false);
    }

    public function test_kendaraan_tanpa_tarif_atau_unit_tidak_tampil(): void
    {
        $tanpaTarif = TipeKendaraan::create(['nama_terjemahan' => ['en' => 'Ghost Car', 'id' => 'Ghost Car'], 'jenis' => 'mobil', 'kursi' => 4, 'transmisi' => 'manual', 'bbm' => 'bensin']);
        $tanpaTarif->unit()->create(['plat_nomor' => 'DK 9 ZZ', 'lokasi_id' => $this->lokasi->id]);

        $this->get(route('sewa.index'))->assertOk()->assertSee('Toyota Avanza')->assertDontSee('Ghost Car');
    }

    public function test_pencarian_lengkap_menampilkan_total_harga(): void
    {
        $this->get(route('sewa.index', $this->cari(52)))
            ->assertOk()
            ->assertSee('Rp1.075.000', false)
            ->assertSee('2 days 4 hours');
    }

    public function test_detail_menampilkan_rincian_harga(): void
    {
        // 2 hari + 4 jam: 2 × 400rb + 275rb (sisa ≤ 12 jam, tanpa tarif per jam).
        $this->get(route('sewa.show', ['kendaraan' => $this->avanza] + $this->cari(52)))
            ->assertOk()
            ->assertSee('Rp1.075.000', false)
            ->assertSee('Sign in to book');
    }

    public function test_isian_waktu_salah_ditampilkan_sebagai_pesan(): void
    {
        $cari = $this->cari();
        $cari['selesai'] = $cari['mulai'];

        $this->get(route('sewa.index', $cari))->assertOk()->assertSee('Return must be after pick-up.');
    }

    public function test_tamu_harus_masuk_untuk_memesan(): void
    {
        $this->post(route('sewa.pesan', ['kendaraan' => $this->avanza] + $this->cari()), ['nama_penyewa' => 'X', 'telepon' => '0812345'])
            ->assertRedirect(route('login'));
        $this->assertSame(0, Order::count());
    }

    public function test_booking_lepas_kunci_dengan_dokumen(): void
    {
        $user = $this->penyewa();

        $res = $this->pesan($user, $this->cari(48), ['catatan' => 'Flight lands 08:30']);

        $order = Order::firstOrFail();
        $res->assertRedirect(route('akun.pesanan.show', $order));
        $this->assertSame(Order::STATUS_MENUNGGU_PEMBAYARAN, $order->status);
        $this->assertSame('sewa', $order->sumber_order);
        $this->assertSame(800000.0, (float) $order->total_idr);

        $b = $order->bookingSewa;
        $this->assertSame($this->unit->id, $b->unit_kendaraan_id);
        $this->assertSame('Flight lands 08:30', $b->catatan);
        $this->assertSame(2, $b->rincian['hari']);
        Storage::disk('local')->assertExists($b->dokumen['identitas']);
        Storage::disk('local')->assertExists($b->dokumen['sim']);

        $this->get(route('akun.pesanan.show', $order))->assertOk()->assertSee('Your rental')->assertSee('Kuta pool');
    }

    public function test_lepas_kunci_wajib_dokumen(): void
    {
        $user = $this->penyewa();

        $this->actingAs($user, 'web')
            ->post(route('sewa.pesan', ['kendaraan' => $this->avanza] + $this->cari()), ['nama_penyewa' => 'Chen', 'telepon' => '0812345678'])
            ->assertSessionHasErrors(['identitas', 'sim']);
        $this->assertSame(0, Order::count());
    }

    public function test_dengan_sopir_tanpa_dokumen(): void
    {
        $this->pesan($this->penyewa(), $this->cari(24, Tarif::SOPIR))->assertRedirect();

        $this->assertSame(900000.0, (float) Order::firstOrFail()->total_idr);
        $this->assertNull(BookingSewa::firstOrFail()->dokumen);
    }

    public function test_unit_penuh_tidak_bisa_dipesan_lagi_dan_bebas_setelah_batal(): void
    {
        $cari = $this->cari(48);
        $this->pesan($this->penyewa(), $cari)->assertRedirect();

        // Unit satu-satunya sudah dipakai: daftar & detail menandai penuh, pesan ditolak.
        $this->get(route('sewa.index', $cari))->assertSee('Not available for these dates');
        $this->get(route('sewa.show', ['kendaraan' => $this->avanza] + $cari))->assertSee('fully booked');
        $kedua = $this->penyewa();
        $this->pesan($kedua, $cari)->assertRedirect();
        $this->assertSame(1, Order::count());

        // Jeda 2 jam: mulai 1 jam setelah selesai masih bentrok, 2 jam setelahnya boleh.
        $setelah = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $cari['selesai'], 'Asia/Makassar');
        $this->pesan($kedua, ['mulai' => $setelah->addHour()->format('Y-m-d\TH:i'), 'selesai' => $setelah->addDay()->format('Y-m-d\TH:i')] + $cari);
        $this->assertSame(1, Order::count());
        $this->pesan($kedua, ['mulai' => $setelah->addHours(2)->format('Y-m-d\TH:i'), 'selesai' => $setelah->addDay()->format('Y-m-d\TH:i')] + $cari);
        $this->assertSame(2, Order::count());

        // Booking pertama dibatalkan → unit bebas lagi di tanggal itu.
        Order::query()->oldest()->first()->update(['status' => Order::STATUS_DIBATALKAN]);
        $this->pesan($kedua, $cari)->assertRedirect();
        $this->assertSame(3, Order::count());
    }

    public function test_harga_dihitung_server_bukan_dari_form(): void
    {
        $this->pesan($this->penyewa(), $this->cari(24), ['total' => 1, 'harga_harian' => 1]);

        $this->assertSame(400000.0, (float) Order::firstOrFail()->total_idr);
    }

    public function test_pesan_terlalu_dekat_ditolak(): void
    {
        $mulai = CarbonImmutable::now('Asia/Makassar')->addHour();
        $cari = ['lokasi' => $this->lokasi->id, 'mulai' => $mulai->format('Y-m-d\TH:i'), 'selesai' => $mulai->addDay()->format('Y-m-d\TH:i'), 'mode' => Tarif::LEPAS_KUNCI];

        $this->pesan($this->penyewa(), $cari)->assertSessionHasErrors('mulai');
        $this->assertSame(0, Order::count());
    }
}
