<?php

namespace Tests\Feature;

use App\Filament\Resources\Kendaraan\Pages\EditKendaraan;
use App\Filament\Resources\Kendaraan\RelationManagers\TarifRelationManager;
use App\Filament\Resources\TarifMusim\TarifMusimResource;
use App\Models\Tarif;
use App\Models\TarifMusim;
use App\Models\TipeKendaraan;
use App\Models\User;
use App\Support\HargaSewa;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class TarifTest extends TestCase
{
    use RefreshDatabase;

    private TipeKendaraan $tipe;

    private Tarif $tarif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tipe = TipeKendaraan::create([
            'nama_terjemahan' => ['en' => 'Honda Brio', 'id' => 'Honda Brio'],
            'jenis' => 'mobil', 'kursi' => 4, 'transmisi' => 'otomatis', 'bbm' => 'bensin',
        ]);
        $this->tarif = $this->tipe->tarif()->create([
            'mode' => Tarif::LEPAS_KUNCI, 'harga_harian' => 300000, 'harga_12jam' => 200000,
            'harga_per_jam' => 35000, 'minimal_jam' => 12,
        ]);
    }

    /** Waktu toko (WITA). */
    private function wita(string $waktu): CarbonImmutable
    {
        return CarbonImmutable::parse($waktu, 'Asia/Makassar');
    }

    private function hitung(string $mulai, string $selesai): array
    {
        return HargaSewa::hitung($this->tarif->fresh(), $this->wita($mulai), $this->wita($selesai));
    }

    public function test_tepat_satu_hari(): void
    {
        $h = $this->hitung('2026-11-02 09:00', '2026-11-03 09:00');

        $this->assertSame(1, $h['hari']);
        $this->assertSame(0, $h['sisa_jam']);
        $this->assertSame(300000.0, $h['total']);
    }

    public function test_sisa_jam_memakai_yang_termurah(): void
    {
        // 26 jam: 1 hari + 2 jam × 35rb (lebih murah dari 12 jam).
        $this->assertSame(370000.0, $this->hitung('2026-11-02 09:00', '2026-11-03 11:00')['total']);
        // 30 jam: 1 hari + harga 12 jam (6 × 35rb = 210rb > 200rb).
        $this->assertSame(500000.0, $this->hitung('2026-11-02 09:00', '2026-11-03 15:00')['total']);
        // 44 jam: sisa 20 jam → harian (20 × 35rb = 700rb).
        $this->assertSame(600000.0, $this->hitung('2026-11-02 09:00', '2026-11-04 05:00')['total']);
    }

    public function test_menit_dibulatkan_ke_atas_per_jam(): void
    {
        $h = $this->hitung('2026-11-02 09:00', '2026-11-03 09:10');

        $this->assertSame(25, $h['durasi_jam']);
        $this->assertSame(335000.0, $h['total']);
    }

    public function test_minimal_durasi(): void
    {
        $h = $this->hitung('2026-11-02 09:00', '2026-11-02 14:00');

        $this->assertSame(5, $h['durasi_jam']);
        $this->assertSame(12, $h['jam_ditagih']);
        $this->assertSame(200000.0, $h['total']);
    }

    public function test_musim_ramai_per_hari(): void
    {
        TarifMusim::create(['nama' => 'Natal', 'mulai' => '2026-12-20', 'selesai' => '2027-01-05', 'kenaikan_persen' => 30]);
        TarifMusim::create(['nama' => 'Nonaktif', 'mulai' => '2026-12-01', 'selesai' => '2026-12-31', 'kenaikan_persen' => 90, 'is_active' => false]);

        // Hari 1 (19 Des) normal, hari 2-3 (20 & 21 Des) +30%.
        $h = $this->hitung('2026-12-19 10:00', '2026-12-22 10:00');

        $this->assertSame(900000.0, $h['harga_dasar']);
        $this->assertSame(180000.0, $h['tambahan_musim']);
        $this->assertSame(1080000.0, $h['total']);
        $this->assertSame(['Natal'], $h['musim']);
    }

    public function test_musim_tumpang_tindih_pakai_kenaikan_terbesar(): void
    {
        TarifMusim::create(['nama' => 'A', 'mulai' => '2026-12-24', 'selesai' => '2026-12-26', 'kenaikan_persen' => 10]);
        TarifMusim::create(['nama' => 'B', 'mulai' => '2026-12-25', 'selesai' => '2026-12-25', 'kenaikan_persen' => 50]);

        $this->assertSame(450000.0, $this->hitung('2026-12-25 08:00', '2026-12-26 08:00')['total']);
    }

    public function test_waktu_kembali_harus_setelah_ambil(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->hitung('2026-11-02 09:00', '2026-11-02 09:00');
    }

    public function test_admin_tambah_tarif_dan_mode_tidak_boleh_ganda(): void
    {
        $_ENV['ADMIN_EMAIL'] = $_SERVER['ADMIN_EMAIL'] = 'super@toko.test';
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = 'rahasia-123';
        $this->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel('admin');
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->actingAs($owner, 'admin');

        $rm = fn () => Livewire::test(TarifRelationManager::class, ['ownerRecord' => $this->tipe, 'pageClass' => EditKendaraan::class]);

        // Mode yang sudah punya tarif ditolak.
        $rm()->callAction(TestAction::make(CreateAction::class)->table(), [
            'mode' => Tarif::LEPAS_KUNCI, 'harga_harian' => 1, 'minimal_jam' => 12,
        ])->assertHasFormErrors(['mode' => 'unique']);

        $rm()->callAction(TestAction::make(CreateAction::class)->table(), [
            'mode' => Tarif::SOPIR, 'harga_harian' => 750000, 'harga_12jam' => 550000, 'minimal_jam' => 12, 'termasuk_bbm' => true,
        ])->assertHasNoFormErrors();

        $this->assertTrue($this->tipe->tarifUntuk(Tarif::SOPIR)->termasuk_bbm);
        $this->assertSame(300000.0, $this->tipe->fresh()->hargaMulai());

        // Kedua mode sudah terisi: tombol tambah disembunyikan.
        $rm()->assertActionHidden(TestAction::make(CreateAction::class)->table());

        $this->get(TarifMusimResource::getUrl('index'))->assertOk();
    }
}
