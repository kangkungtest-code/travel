<?php

namespace Tests\Feature;

use App\Filament\Pages\KontakNotifikasi;
use App\Filament\Pages\PengaturanPembayaran;
use App\Models\Payment;
use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\User;
use App\Payments\MetodePembayaran;
use App\Support\AkunPembayaran;
use Database\Seeders\RoleAndPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PengaturanPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->pemilik = User::factory()->create();
        $this->pemilik->assignRole('Owner');
        Filament::setCurrentPanel('admin');

        // Kondisi server demo: kunci dari .env (secret GitHub).
        config([
            'services.xendit.secret_key' => 'xnd_development_dariserver',
            'services.xendit.callback_token' => 'token-server',
            'services.paypal.client_id' => null,
            'services.paypal.client_secret' => null,
        ]);
    }

    public function test_hanya_yang_berizin_bisa_membuka(): void
    {
        $staf = User::factory()->create();
        Role::findOrCreate('Gudang', 'admin')->syncPermissions(['stok.edit']);
        $staf->assignRole('Gudang');

        $this->actingAs($staf, 'admin')->get(PengaturanPembayaran::getUrl())->assertForbidden();
        $this->actingAs($this->pemilik, 'admin')->get(PengaturanPembayaran::getUrl())
            ->assertOk()
            ->assertSee('Sekarang memakai nilai dari server')
            ->assertSee(route('webhook.xendit'))
            ->assertDontSee('xnd_development_dariserver'); // kunci tidak pernah tampil utuh
    }

    public function test_kunci_panel_terenkripsi_dan_menggantikan_nilai_server(): void
    {
        $this->actingAs($this->pemilik, 'admin');

        Livewire::test(PengaturanPembayaran::class)
            ->fillForm([
                'xendit_secret_key' => 'xnd_development_milik_toko_123456',
                'xendit_callback_token' => 'token-toko',
                'va_banks' => ['BCA', 'BRI'],
                'paypal_client_id' => 'pp-id', 'paypal_client_secret' => 'pp-rahasia', 'paypal_mode' => 'sandbox',
            ])
            ->call('simpan')
            ->assertHasNoFormErrors()
            ->assertNotified('Tersimpan');

        $mentah = DB::table('pengaturan')->where('kunci', 'bayar.xendit.secret_key')->value('nilai');
        $this->assertStringNotContainsString('milik_toko', $mentah);
        $this->assertSame('xnd_development_milik_toko_123456', AkunPembayaran::nilai('xendit.secret_key'));
        $this->assertSame('panel', AkunPembayaran::sumber('xendit.secret_key'));
        $this->assertSame(['BCA', 'BRI'], AkunPembayaran::bankVa());
        $this->assertTrue(MetodePembayaran::ditawarkan(Payment::GATEWAY_PAYPAL));

        // Gateway memakai kunci dari panel.
        Http::fake(['api.xendit.co/*' => Http::response(['payment_request_id' => 'pr-1', 'actions' => []], 201)]);
        $this->assertTrue(MetodePembayaran::gateway(Payment::GATEWAY_QRIS)->aktif());

        // Simpan lagi dengan isian rahasia kosong: tidak berubah.
        Livewire::test(PengaturanPembayaran::class)
            ->assertFormSet(['xendit_secret_key' => null, 'paypal_client_id' => 'pp-id'])
            ->call('simpan');
        $this->assertSame('xnd_development_milik_toko_123456', AkunPembayaran::nilai('xendit.secret_key'));

        // Hapus: kembali ke nilai server.
        Livewire::test(PengaturanPembayaran::class)
            ->fillForm(['hapus_xendit_secret_key' => true])
            ->call('simpan');
        $this->assertSame('xnd_development_dariserver', AkunPembayaran::nilai('xendit.secret_key'));
        $this->assertSame('server', AkunPembayaran::sumber('xendit.secret_key'));
    }

    public function test_metode_dimatikan_tidak_ditawarkan_tapi_webhook_tetap_jalan(): void
    {
        $this->actingAs($this->pemilik, 'admin');
        $this->assertTrue(MetodePembayaran::ditawarkan(Payment::GATEWAY_VA));

        Livewire::test(PengaturanPembayaran::class)
            ->fillForm(['aktif_'.Payment::GATEWAY_VA => false])
            ->call('simpan');

        $this->assertFalse(MetodePembayaran::ditawarkan(Payment::GATEWAY_VA));
        $this->assertTrue(MetodePembayaran::ditawarkan(Payment::GATEWAY_QRIS));
        $this->assertNotNull(MetodePembayaran::gateway(Payment::GATEWAY_VA)); // untuk webhook & refund

        // Semua bank tidak dicentang = VA juga tidak ditawarkan.
        Livewire::test(PengaturanPembayaran::class)
            ->fillForm(['aktif_'.Payment::GATEWAY_VA => true, 'va_banks' => []])
            ->call('simpan');
        $this->assertSame([], AkunPembayaran::bankVa());
        $this->assertFalse(MetodePembayaran::ditawarkan(Payment::GATEWAY_VA));
    }

    public function test_tes_koneksi_xendit(): void
    {
        $this->actingAs($this->pemilik, 'admin');

        Http::fake(['api.xendit.co/balance' => Http::sequence()
            ->push(['message' => 'forbidden'], 403)
            ->push(['error_code' => 'INVALID_API_KEY'], 401)]);
        Livewire::test(PengaturanPembayaran::class)->callAction('tesXendit')->assertNotified('Xendit terhubung');
        $this->assertFalse(AkunPembayaran::tesXendit('xnd_development_salah')['ok']);
    }

    public function test_kunci_tidak_bisa_dibaca_setelah_app_key_berubah_dianggap_kosong(): void
    {
        Pengaturan::simpan('bayar.xendit.secret_key', 'bukan-hasil-enkripsi');

        $this->assertNull(AkunPembayaran::dariPanel('xendit.secret_key'));
        $this->assertSame('xnd_development_dariserver', AkunPembayaran::nilai('xendit.secret_key'));
    }

    public function test_alamat_retur_dari_panel(): void
    {
        $this->actingAs($this->pemilik, 'admin');
        config(['toko.retur.alamat' => 'Alamat dari server']);

        Livewire::test(KontakNotifikasi::class)
            ->assertFormSet(['alamat_retur' => 'Alamat dari server'])
            ->fillForm(['alamat_retur' => "Gudang Toko\nJl. Mawar 1, Makassar"])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertSame("Gudang Toko\nJl. Mawar 1, Makassar", Pengaturan::ambil('retur.alamat'));
    }
}
