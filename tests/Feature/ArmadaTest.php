<?php

namespace Tests\Feature;

use App\Filament\Resources\Kendaraan\KendaraanResource;
use App\Filament\Resources\Kendaraan\Pages\CreateKendaraan;
use App\Filament\Resources\Kendaraan\Pages\EditKendaraan;
use App\Filament\Resources\Kendaraan\RelationManagers\UnitRelationManager;
use App\Filament\Resources\Lokasi\LokasiResource;
use App\Models\Lokasi;
use App\Models\TipeKendaraan;
use App\Models\UnitKendaraan;
use App\Models\User;
use Database\Seeders\ArmadaDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ArmadaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['ADMIN_EMAIL'] = $_SERVER['ADMIN_EMAIL'] = 'super@toko.test';
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = 'rahasia-123';
        $this->seed(DatabaseSeeder::class);

        Storage::fake('public');
        Filament::setCurrentPanel('admin');

        $this->owner = User::factory()->create(['email' => 'pemilik@toko.test']);
        $this->owner->assignRole('Owner');
        $this->actingAs($this->owner, 'admin');
    }

    private function lokasi(): Lokasi
    {
        return Lokasi::create(['nama_terjemahan' => ['id' => 'Pool Kuta', 'en' => 'Kuta pool'], 'kota' => 'Kuta']);
    }

    private function tipe(): TipeKendaraan
    {
        return TipeKendaraan::create([
            'nama_terjemahan' => ['en' => 'Daihatsu Xenia', 'id' => 'Daihatsu Xenia'],
            'jenis' => 'mobil', 'kursi' => 7, 'transmisi' => 'manual', 'bbm' => 'bensin',
        ]);
    }

    public function test_halaman_armada_bisa_dibuka_owner(): void
    {
        $this->tipe();
        $this->lokasi();

        $this->get(KendaraanResource::getUrl('index'))->assertOk()->assertSee('Daihatsu Xenia');
        $this->get(LokasiResource::getUrl('index'))->assertOk()->assertSee('Pool Kuta');
    }

    public function test_tanpa_izin_armada_ditolak(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');

        $this->get(KendaraanResource::getUrl('index'))->assertForbidden();
    }

    public function test_owner_punya_izin_armada(): void
    {
        $this->assertTrue($this->owner->fresh()->hasPermissionTo('armada.kelola'));
    }

    public function test_membuat_kendaraan_dengan_spesifikasi(): void
    {
        Livewire::test(CreateKendaraan::class)
            ->fillForm([
                'nama_terjemahan' => ['en' => 'Toyota Avanza', 'id' => 'Toyota Avanza', 'zh_TW' => null],
                'deskripsi_terjemahan' => ['en' => 'Family car', 'id' => 'Mobil keluarga', 'zh_TW' => null],
                'jenis' => 'mobil',
                'kursi' => 7,
                'transmisi' => 'otomatis',
                'bbm' => 'bensin',
                'bagasi' => 2,
                'fasilitas' => ['ac', 'usb'],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tipe = TipeKendaraan::firstOrFail();
        $this->assertSame('toyota-avanza', $tipe->slug);
        $this->assertSame(['ac', 'usb'], $tipe->fasilitas);
        $this->assertSame('Mobil · 7 kursi · Otomatis · Bensin', $tipe->ringkasan('id'));
        $this->assertSame('Car · 7 seats · Automatic · Petrol', $tipe->ringkasan('en'));
    }

    public function test_nama_dan_spesifikasi_wajib(): void
    {
        Livewire::test(CreateKendaraan::class)
            ->fillForm(['nama_terjemahan' => ['en' => null, 'id' => null], 'kursi' => null])
            ->call('create')
            ->assertHasFormErrors(['nama_terjemahan.en' => 'required', 'nama_terjemahan.id' => 'required', 'kursi' => 'required']);
    }

    public function test_tambah_unit_plat_dirapikan_dan_unik(): void
    {
        $tipe = $this->tipe();
        $lokasi = $this->lokasi();

        $rm = fn () => Livewire::test(UnitRelationManager::class, ['ownerRecord' => $tipe, 'pageClass' => EditKendaraan::class]);

        $rm()->callAction(TestAction::make(CreateAction::class)->table(), [
            'plat_nomor' => ' dk  1234   ab ',
            'lokasi_id' => $lokasi->id,
            'tahun' => 2023,
            'status' => UnitKendaraan::SIAP,
        ])->assertHasNoFormErrors();

        $this->assertSame('DK 1234 AB', UnitKendaraan::firstOrFail()->plat_nomor);

        $rm()->callAction(TestAction::make(CreateAction::class)->table(), [
            'plat_nomor' => 'DK 1234 ab',
            'lokasi_id' => $lokasi->id,
            'status' => UnitKendaraan::SIAP,
        ])->assertHasFormErrors(['plat_nomor']);

        $this->assertSame(1, UnitKendaraan::count());
    }

    public function test_hanya_tipe_dengan_unit_siap_yang_tampil(): void
    {
        $tipe = $this->tipe();
        $lokasi = $this->lokasi();
        $this->assertFalse(TipeKendaraan::tampil()->exists());

        $unit = $tipe->unit()->create(['plat_nomor' => 'DK 1 A', 'lokasi_id' => $lokasi->id, 'status' => UnitKendaraan::PERAWATAN]);
        $this->assertFalse(TipeKendaraan::tampil()->exists());

        $unit->update(['status' => UnitKendaraan::SIAP]);
        $this->assertTrue(TipeKendaraan::tampil()->exists());

        $tipe->update(['is_active' => false]);
        $this->assertFalse(TipeKendaraan::tampil()->exists());
    }

    public function test_halaman_edit_menampilkan_semua_bahasa(): void
    {
        $tipe = $this->tipe();

        Livewire::test(EditKendaraan::class, ['record' => $tipe->getRouteKey()])
            ->assertOk()
            ->assertSchemaStateSet(['nama_terjemahan.en' => 'Daihatsu Xenia', 'kursi' => 7]);
    }

    public function test_seeder_armada_demo_idempotent(): void
    {
        $this->seed(ArmadaDemoSeeder::class);
        $tipe = TipeKendaraan::count();
        $unit = UnitKendaraan::count();
        $this->seed(ArmadaDemoSeeder::class);

        $this->assertGreaterThan(0, $tipe);
        $this->assertSame($tipe, TipeKendaraan::count());
        $this->assertSame($unit, UnitKendaraan::count());
    }
}
