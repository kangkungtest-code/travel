<?php

namespace Tests\Feature;

use App\Actions\Order\BuatOrderAction;
use App\Filament\Pages\ChatbotKontak;
use App\Filament\Pages\FiturPaket;
use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Models\Payment;
use App\Models\Pengaturan;
use App\Models\User;
use App\Payments\MetodePembayaran;
use App\Support\Fitur;
use App\Support\Oauth\ProviderSosial;
use Database\Seeders\RoleAndPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Concerns\TokoFixture;
use Tests\TestCase;

class FiturPaketTest extends TestCase
{
    use RefreshDatabase, TokoFixture;

    private User $super;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->siapkanToko();
        Filament::setCurrentPanel('admin');

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
        $this->super = User::factory()->create();
        $this->super->assignRole(RoleAndPermissionSeeder::SUPER_ADMIN);

        config([
            'services.google' => ['client_id' => 'g-id', 'client_secret' => 'g-secret'],
            'services.paypal' => ['client_id' => 'pp-id', 'client_secret' => 'pp-secret', 'webhook_id' => 'WH', 'mode' => 'sandbox'],
        ]);
    }

    private function pilih(int $paket, array $tambahan = [])
    {
        $this->actingAs($this->super, 'admin');

        return Livewire::test(FiturPaket::class)
            ->fillForm(['paket' => (string) $paket, 'tambahan' => $tambahan])
            ->call('simpan');
    }

    public function test_belum_diatur_semua_fitur_menyala(): void
    {
        $this->assertNull(Fitur::paket());
        $this->assertSame(Fitur::siap(), Fitur::semuaAktif());
        $this->assertTrue(Fitur::aktif('chatbot'));
        $this->assertTrue(Fitur::aktif('katalog_apa_saja')); // bukan fitur bersaklar = inti
        $this->assertFalse(in_array('laporan_lengkap', Fitur::semuaAktif(), true)); // belum dibangun
    }

    public function test_hanya_super_admin_yang_bisa_mengatur(): void
    {
        $this->assertFalse($this->owner->hasPermissionTo('fitur.kelola'));
        $this->actingAs($this->owner, 'admin')->get(FiturPaket::getUrl())->assertForbidden();
        $this->actingAs($this->super, 'admin')->get(FiturPaket::getUrl())->assertOk()->assertSee('Paket belum pernah dipilih');
    }

    public function test_paket_1_mematikan_fitur_di_toko_panel_dan_api(): void
    {
        $this->pilih(1)->assertHasNoFormErrors()->assertNotified('Tersimpan');

        $this->assertSame(1, Fitur::paket());
        $this->assertSame([], Fitur::semuaAktif());

        // Storefront & route.
        auth('admin')->logout();
        $this->get('/')->assertOk()->assertDontSee('data-chat', false);
        $this->get(route('chatbot'))->assertNotFound();
        $this->assertSame([], ProviderSosial::aktif());
        $this->get('/masuk/google')->assertNotFound();

        // Pembayaran: PayPal tidak ditawarkan walau kuncinya ada; QRIS tetap (inti).
        $this->assertFalse(MetodePembayaran::ditawarkan(Payment::GATEWAY_PAYPAL));

        // API aplikasi mobile.
        $this->postJson('/api/v1/admin/masuk', ['email' => 'x@y.z', 'password' => 'x'])->assertNotFound();

        // Panel: menu Retur & Chatbot hilang (datanya tetap di database).
        $this->actingAs($this->owner, 'admin');
        $this->assertFalse(ReturnRequestResource::canAccess());
        $this->assertFalse(ChatbotKontak::canAccess());

        // Riwayat tercatat.
        $r = DB::table('riwayat_fitur')->first();
        $this->assertNull($r->paket_dari);
        $this->assertSame(1, (int) $r->paket_ke);
        $this->assertContains('chatbot', json_decode($r->dimatikan, true));
        $this->assertSame($this->super->id, $r->user_id);
    }

    public function test_fitur_tambahan_dan_upgrade(): void
    {
        $this->pilih(1, ['chatbot']);
        $this->assertSame(['chatbot'], Fitur::semuaAktif());
        $this->get(route('chatbot'))->assertOk();

        $this->pilih(2, ['chatbot', 'paypal']);
        // chatbot sudah termasuk paket 2, jadi tidak disimpan sebagai tambahan.
        $this->assertSame(['paypal'], Fitur::tambahan());
        $this->assertTrue(Fitur::aktif('retur'));
        $this->assertTrue(Fitur::aktif('paypal'));
        $this->assertFalse(Fitur::aktif('multi_bahasa'));
    }

    public function test_downgrade_ditahan_kalau_ada_transaksi_berjalan(): void
    {
        $this->pilih(3);
        $user = $this->pembeli();
        $user->cart()->firstOrCreate()->items()->create(['variant_id' => $this->kaosM->id, 'qty' => 1]);
        $order = app(BuatOrderAction::class)->execute($user, $this->alamat($user), 'IDR');
        $order->payments()->create([
            'gateway' => Payment::GATEWAY_PAYPAL, 'status' => Payment::PENDING, 'mata_uang' => 'USD',
            'jumlah' => 7, 'kurs_terpakai' => 0.00006, 'jumlah_idr' => 120000,
        ]);

        $this->pilih(2)->assertNotified('Belum bisa disimpan');
        $this->assertSame(3, Fitur::paket());
        $this->assertSame(['paypal' => ['1 pembayaran PayPal masih menunggu']], Fitur::terapkan(2, []));

        // Tetap boleh kalau PayPal dipertahankan sebagai tambahan.
        $this->assertSame([], Fitur::terapkan(2, ['paypal']));
        $this->assertSame(2, Fitur::paket());
    }

    public function test_nama_paket_bisa_diganti(): void
    {
        $this->actingAs($this->super, 'admin');
        Livewire::test(FiturPaket::class)
            ->fillForm(['paket' => '2', 'nama_paket_2' => 'Tunas'])
            ->call('simpan');

        $this->assertSame('Tunas', Fitur::namaPaket(2));
        $this->assertSame('Paket 1', Fitur::namaPaket(1));
    }

    public function test_isian_tersembunyi_tidak_terhapus_saat_simpan(): void
    {
        Pengaturan::simpan('email_pemilik.alamat', 'pemilik@toko.test');
        $this->pilih(1);

        $this->actingAs($this->owner, 'admin');
        Livewire::test(\App\Filament\Pages\KontakNotifikasi::class)->call('simpan')->assertHasNoFormErrors();

        $this->assertSame('pemilik@toko.test', Pengaturan::ambil('email_pemilik.alamat'));
    }

    public function test_perintah_buat_super_admin(): void
    {
        putenv('SUPERADMIN_PASSWORD=rahasia-super-123');
        try {
            $this->artisan('toko:super-admin', ['email' => 'Frendi@Contoh.com'])->assertSuccessful();
        } finally {
            putenv('SUPERADMIN_PASSWORD');
        }

        $u = User::where('email', 'frendi@contoh.com')->firstOrFail();
        $this->assertTrue($u->hasRole(RoleAndPermissionSeeder::SUPER_ADMIN));
        $this->assertTrue($u->hasPermissionTo('fitur.kelola'));

        // Akun yang sudah ada (mis. admin Owner) bisa dijadikan Super Admin tanpa password.
        $this->artisan('toko:super-admin', ['email' => $this->owner->email])->assertSuccessful();
        $this->assertTrue($this->owner->fresh()->hasRole(RoleAndPermissionSeeder::SUPER_ADMIN));

        $this->artisan('toko:super-admin', ['email' => 'baru@contoh.com'])->assertFailed();

        // Cabut peran dari akun Owner; akunnya tetap ada & tetap Owner.
        $this->artisan('toko:super-admin', ['email' => $this->owner->email, '--cabut' => true])->assertSuccessful();
        $this->assertFalse($this->owner->fresh()->hasRole(RoleAndPermissionSeeder::SUPER_ADMIN));
        $this->assertTrue($this->owner->fresh()->hasRole('Owner'));
    }

    public function test_paket_1_toko_indonesia_saja_bahasa_rupiah_dan_pengiriman(): void
    {
        $this->assertCount(3, config('toko.locales'));
        $this->pilih(1);
        auth('admin')->logout();

        $this->assertSame(['id' => 'Bahasa Indonesia'], config('toko.locales'));
        $this->assertSame(['id'], config('toko.required_locales'));
        $this->assertSame(['IDR'], config('toko.currencies'));
        $this->assertSame(['ID' => 'Indonesia'], config('toko.negara'));
        $this->assertCount(3, Fitur::semuaBahasa()); // data bahasa lain tetap dikenali

        // Pemilih bahasa & mata uang hilang; toko tampil dalam Bahasa Indonesia & Rupiah walau sesi minta lain.
        $this->withSession(['locale' => 'en', 'currency' => 'USD'])->get('/')
            ->assertOk()->assertDontSee('name="locale"', false)->assertDontSee('name="currency"', false);
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('IDR', \App\Support\TampilanProduk::mataUang());

        // Zona Taiwan (kalau ada) diabaikan, tidak dihapus.
        \App\Models\ShippingZone::create(['nama' => 'Taiwan', 'negara' => ['TW'], 'is_active' => true]);
        $this->assertNull(\App\Models\ShippingZone::untukNegara('TW'));
        $this->assertNotContains('TW', \App\Models\ShippingZone::negaraTersedia());
        $this->assertSame(1, \App\Models\ShippingZone::where('nama', 'Taiwan')->count());

        // Naik ke paket 3: semuanya kembali.
        $this->pilih(3);
        $this->assertCount(3, config('toko.locales'));
        $this->assertSame(['USD', 'IDR', 'TWD'], config('toko.currencies'));
        $this->assertSame('TW', \App\Models\ShippingZone::untukNegara('TW')?->negara[0]);
    }

    public function test_downgrade_ditahan_untuk_pesanan_mata_uang_asing_dan_luar_negeri(): void
    {
        $this->pilih(3);
        \App\Models\ExchangeRate::create(['mata_uang_asal' => 'IDR', 'mata_uang_tujuan' => 'USD', 'rate' => 0.00006, 'margin_persen' => 0, 'sumber' => 'manual', 'berlaku_dari' => now()->subDay()]);
        \App\Models\ShippingZone::create(['nama' => 'Taiwan', 'negara' => ['TW'], 'is_active' => true])
            ->rates()->create(['berat_min_gram' => 0, 'berat_max_gram' => 5000, 'tarif_idr' => 200000]);

        $user = $this->pembeli();
        $user->cart()->firstOrCreate()->items()->create(['variant_id' => $this->kaosM->id, 'qty' => 1]);
        app(BuatOrderAction::class)->execute($user, $this->alamat($user, 'TW'), 'USD');

        $alasan = Fitur::terapkan(1, []);
        $this->assertSame(['1 pesanan dalam mata uang asing belum dibayar'], $alasan['multi_mata_uang']);
        $this->assertSame(['1 pesanan ke luar negeri belum selesai'], $alasan['kirim_luar_negeri']);
        $this->assertArrayNotHasKey('multi_bahasa', $alasan);
        $this->assertSame(3, Fitur::paket());
    }

    public function test_super_admin_tidak_bisa_masuk_ke_data_toko(): void
    {
        $this->actingAs($this->super, 'admin');

        $this->get(\App\Filament\Resources\Products\ProductResource::getUrl())->assertForbidden();
        $this->get(\App\Filament\Resources\Orders\OrderResource::getUrl())->assertForbidden();
        $this->get(\App\Filament\Pages\PengaturanPembayaran::getUrl())->assertForbidden();
        $this->get(\App\Filament\Pages\KontakNotifikasi::getUrl())->assertForbidden();
        $this->get(FiturPaket::getUrl())->assertOk();

        // Masuk ke /admin (dashboard) langsung diarahkan ke Fitur & paket, tanpa data penjualan.
        $this->get('/admin')->assertRedirect(FiturPaket::getUrl());
        $this->assertSame([], (new \App\Filament\Pages\Dashboard)->getWidgets());

        // Owner tetap bisa semuanya kecuali Fitur & paket.
        $this->actingAs($this->owner, 'admin');
        $this->get(\App\Filament\Pages\Dashboard::getUrl())->assertOk();
        $this->get(FiturPaket::getUrl())->assertForbidden();
    }
}
