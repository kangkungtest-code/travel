<?php

namespace Tests\Feature;

use App\Filament\Resources\HalamanKebijakans\HalamanKebijakanResource;
use App\Filament\Resources\HalamanKebijakans\Pages\EditHalamanKebijakan;
use App\Models\HalamanKebijakan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KebijakanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['ADMIN_EMAIL'] = $_SERVER['ADMIN_EMAIL'] = 'owner@toko.test';
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = 'rahasia-123';
        $this->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel('admin');
        $this->admin = User::factory()->create(['email' => 'pemilik-toko@toko.test']);
        $this->admin->assignRole('Owner');
    }

    public function test_seeder_membuat_empat_halaman_dengan_nilai_dari_config(): void
    {
        $this->assertSame(['terms', 'returns', 'shipping', 'privacy'], HalamanKebijakan::query()->tampil()->pluck('slug')->all());

        $retur = HalamanKebijakan::where('slug', 'returns')->first();
        $this->assertStringContainsString('**7 days**', $retur->getTranslation('isi_terjemahan', 'en'));
        $this->assertStringContainsString('**7 hari**', $retur->getTranslation('isi_terjemahan', 'id'));
        $this->assertStringNotContainsString('{hari}', $retur->getTranslation('isi_terjemahan', 'zh_TW'));
        $this->assertGreaterThan(0, $retur->jumlahPerluDiisi());

        // Dijalankan ulang (tiap deploy): isi buatan admin tidak ditimpa.
        $retur->update(['isi_terjemahan' => ['en' => 'Edited', 'id' => 'Diubah']]);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame('Edited', $retur->fresh()->getTranslation('isi_terjemahan', 'en'));
        $this->assertSame(4, HalamanKebijakan::count());
    }

    public function test_halaman_tampil_di_toko_dengan_markdown_aman(): void
    {
        HalamanKebijakan::where('slug', 'privacy')->first()->update([
            'isi_terjemahan' => ['en' => "## Data\n- **Email**\n\n<script>alert(1)</script> [x](javascript:alert(1))"],
        ]);

        $this->get(route('kebijakan', 'privacy'))->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('<h2>Data</h2>', false)
            ->assertSee('<strong>Email</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false);

        // Bahasa lain.
        $this->withSession(['locale' => 'id'])->get(route('kebijakan', 'returns'))->assertOk()->assertSee('Retur & Refund');

        // Tautan di footer.
        $this->get(route('faq'))->assertSee(route('kebijakan', 'terms'), false)->assertSee('Kebijakan Pengiriman');
    }

    public function test_halaman_disembunyikan_tidak_bisa_dibuka(): void
    {
        HalamanKebijakan::where('slug', 'shipping')->update(['is_active' => false]);

        $this->get(route('kebijakan', 'shipping'))->assertNotFound();
        $this->get(route('kebijakan', 'tidak-ada'))->assertNotFound();
        $this->get(route('faq'))->assertDontSee(route('kebijakan', 'shipping'), false);
    }

    public function test_catatan_persetujuan_di_halaman_daftar(): void
    {
        $this->get(route('daftar'))
            ->assertSee('By creating an account you agree to our', false)
            ->assertSee(route('kebijakan', 'privacy'), false);
    }

    public function test_admin_mengedit_halaman(): void
    {
        $this->actingAs($this->admin, 'admin');
        $h = HalamanKebijakan::where('slug', 'terms')->first();

        $this->get(HalamanKebijakanResource::getUrl('index'))->assertOk()->assertSee('Syarat & Ketentuan')->assertSee('per bahasa');

        Livewire::test(EditHalamanKebijakan::class, ['record' => $h->getRouteKey()])
            ->assertSchemaStateSet(['judul_terjemahan.en' => 'Terms & Conditions'])
            ->fillForm([
                'judul_terjemahan.id' => 'Syarat Belanja',
                'isi_terjemahan.id' => "## Umum\nSudah lengkap.",
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $h->refresh();
        $this->assertSame('Syarat Belanja', $h->getTranslation('judul_terjemahan', 'id'));
        $this->assertSame('Terms & Conditions', $h->getTranslation('judul_terjemahan', 'en'));

        // Halaman tetap: tidak bisa dibuat / dihapus dari panel.
        $this->assertFalse($this->admin->can('create', HalamanKebijakan::class));
        $this->assertFalse($this->admin->can('delete', $h));
        $this->assertTrue($this->admin->can('update', $h));
    }
}
