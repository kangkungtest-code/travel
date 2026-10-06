<?php

namespace Tests\Feature;

use App\Actions\Order\BuatOrderAction;
use App\Actions\Retur\AjukanReturAction;
use App\Actions\Order\UbahStatusOrderAction;
use App\Models\Order;
use App\Models\PerangkatAdmin;
use App\Models\Role;
use App\Models\User;
use App\Notifications\KabarAdmin;
use App\Support\NotifikasiAdmin;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\TokoFixture;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase, TokoFixture;

    private User $admin;

    private User $pembeliA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->siapkanToko();

        $this->admin = User::factory()->create(['email' => 'owner@toko.test', 'password' => 'rahasia-123']);
        $this->admin->assignRole('Owner');
        $this->pembeliA = $this->pembeli();
    }

    private function order(int $qty = 2): Order
    {
        $cart = $this->pembeliA->cart()->firstOrCreate();
        $cart->items()->create(['variant_id' => $this->kaosM->id, 'qty' => $qty]);

        return app(BuatOrderAction::class)->execute($this->pembeliA, $this->alamat($this->pembeliA), 'IDR');
    }

    private function sebagaiAdmin(?User $u = null): void
    {
        Sanctum::actingAs($u ?? $this->admin, ['admin']);
    }

    public function test_masuk_hanya_untuk_admin_dan_keluar_mencabut_token(): void
    {
        $this->postJson('/api/v1/admin/masuk', ['email' => 'owner@toko.test', 'password' => 'salah', 'nama_perangkat' => 'HP'])
            ->assertStatus(422)->assertJson(['message' => 'Email atau password salah.']);

        $this->postJson('/api/v1/admin/masuk', ['email' => $this->pembeliA->email, 'password' => 'password', 'nama_perangkat' => 'HP'])
            ->assertForbidden();

        $res = $this->postJson('/api/v1/admin/masuk', ['email' => 'owner@toko.test', 'password' => 'rahasia-123', 'nama_perangkat' => 'Pixel 8'])
            ->assertOk()
            ->assertJsonPath('admin.email', 'owner@toko.test')
            ->assertJsonPath('admin.peran.0', 'Owner');
        $this->assertContains('order.ubah_status', $res->json('admin.izin'));
        $token = $res->json('token');

        $this->withToken($token)->getJson('/api/v1/admin/saya')->assertOk()->assertJsonPath('data.nama', $this->admin->nama_lengkap);

        // Daftarkan HP, lalu keluar: token & perangkat ikut hilang.
        $this->withToken($token)->postJson('/api/v1/admin/perangkat', ['fcm_token' => 'fcm-abc', 'platform' => 'android'])->assertOk();
        $this->assertSame(1, PerangkatAdmin::count());
        $this->withToken($token)->postJson('/api/v1/admin/keluar')->assertNoContent();
        $this->assertSame(0, PerangkatAdmin::count());
        $this->assertSame(0, $this->admin->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/saya')->assertUnauthorized()
            ->assertJson(['message' => 'Sesi berakhir atau belum masuk. Silakan masuk lagi.']);
    }

    public function test_token_pembeli_atau_role_dicabut_ditolak(): void
    {
        Sanctum::actingAs($this->pembeliA, ['admin']);
        $this->getJson('/api/v1/admin/pesanan')->assertForbidden();

        Sanctum::actingAs($this->admin, ['lain']); // token tanpa kemampuan admin
        $this->getJson('/api/v1/admin/pesanan')->assertForbidden();

        $this->sebagaiAdmin();
        $this->getJson('/api/v1/admin/pesanan')->assertOk();
        $this->admin->syncRoles([]);
        $this->getJson('/api/v1/admin/pesanan')->assertForbidden();
    }

    public function test_izin_per_menu(): void
    {
        $staf = User::factory()->create();
        Role::findOrCreate('Gudang', 'admin')->syncPermissions(['stok.edit']);
        $staf->assignRole('Gudang');
        $this->sebagaiAdmin($staf);

        $this->getJson('/api/v1/admin/stok')->assertOk();
        $this->getJson('/api/v1/admin/pesanan')->assertForbidden()->assertJsonPath('izin', 'order.lihat');
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_alur_pesanan_dari_aplikasi(): void
    {
        Notification::fake();
        $order = $this->order();
        $this->sebagaiAdmin();

        $this->getJson('/api/v1/admin/pesanan/jumlah')->assertOk()->assertJson(['menunggu_bayar' => 1, 'perlu_diproses' => 0]);
        $this->getJson('/api/v1/admin/pesanan?tab=menunggu_bayar&q='.$this->pembeliA->email)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nomor', $order->nomor);
        $this->getJson('/api/v1/admin/pesanan?tab=selesai')->assertJsonCount(0, 'data');

        $detail = $this->getJson("/api/v1/admin/pesanan/{$order->nomor}")->assertOk();
        $detail->assertJsonPath('data.barang.0.sku', 'K-M')
            ->assertJsonPath('data.total_idr.teks', 'Rp220.000')
            ->assertJsonPath('data.kontak_pembeli.whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/628123456789'))
            ->assertJsonPath('data.aksi.0.kode', 'konfirmasi-bayar');

        $this->postJson("/api/v1/admin/pesanan/{$order->nomor}/konfirmasi-bayar", [])->assertStatus(422)->assertJsonValidationErrors('catatan');
        $this->postJson("/api/v1/admin/pesanan/{$order->nomor}/konfirmasi-bayar", ['catatan' => 'Transfer BCA'])
            ->assertOk()->assertJsonPath('data.status', 'dibayar')->assertJsonPath('data.aksi.0.kode', 'proses');

        // Langkah yang tidak urut ditolak dengan pesan bahasa Indonesia.
        $this->postJson("/api/v1/admin/pesanan/{$order->nomor}/selesai")
            ->assertStatus(422)->assertJson(['message' => 'Status pesanan tidak bisa diubah dari dibayar ke selesai.']);

        $this->postJson("/api/v1/admin/pesanan/{$order->nomor}/proses")->assertOk()->assertJsonPath('data.status', 'diproses');
        $this->postJson("/api/v1/admin/pesanan/{$order->nomor}/kirim", ['resi' => ''])->assertStatus(422)
            ->assertJsonPath('errors.resi.0', 'resi wajib diisi.');
        $this->postJson("/api/v1/admin/pesanan/{$order->nomor}/kirim", ['resi' => 'JNE999'])->assertOk()->assertJsonPath('data.resi', 'JNE999');
        $this->postJson("/api/v1/admin/pesanan/{$order->nomor}/selesai")->assertOk()
            ->assertJsonPath('data.status', 'selesai')->assertJsonPath('data.aksi', [])
            ->assertJsonPath('data.riwayat.1.oleh', $this->admin->nama_lengkap);

        $this->getJson('/api/v1/admin/pesanan/TIDAK-ADA')->assertNotFound()->assertJson(['message' => 'Data tidak ditemukan.']);
    }

    public function test_batalkan_hanya_yang_belum_dibayar(): void
    {
        Notification::fake();
        $order = $this->order();
        $this->sebagaiAdmin();

        $this->postJson("/api/v1/admin/pesanan/{$order->nomor}/batalkan", ['catatan' => 'Pembeli minta batal'])
            ->assertOk()->assertJsonPath('data.status', 'dibatalkan');
        $this->assertSame(0, $this->kaosM->fresh()->stocks->first()->jumlah_reserved);

        $lain = $this->order(1);
        app(UbahStatusOrderAction::class)->execute($lain, Order::STATUS_DIBAYAR);
        $this->postJson("/api/v1/admin/pesanan/{$lain->nomor}/batalkan", ['catatan' => 'x'])->assertStatus(422);
    }

    public function test_stok_cari_scan_restock_dan_koreksi(): void
    {
        $this->sebagaiAdmin();

        $this->getJson('/api/v1/admin/stok?sku=k-l')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stok.tersedia', 2)->assertJsonPath('data.0.stok.status', 'menipis');
        $this->getJson('/api/v1/admin/stok?q=kaos')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/stok?filter=habis')->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/admin/stok/{$this->kaosL->id}", ['jenis' => 'restock', 'jumlah' => 10])
            ->assertOk()->assertJsonPath('data.stok.fisik', 12)->assertJsonPath('data.stok.status', 'aman');
        $this->postJson("/api/v1/admin/stok/{$this->kaosL->id}", ['jenis' => 'koreksi', 'jumlah' => 7])
            ->assertOk()->assertJsonPath('data.stok.fisik', 7);
        $this->postJson("/api/v1/admin/stok/{$this->kaosL->id}", ['jenis' => 'koreksi', 'jumlah' => 7])->assertStatus(422);
        $this->postJson("/api/v1/admin/stok/{$this->kaosL->id}", ['jenis' => 'restock', 'jumlah' => 0])->assertStatus(422);

        $this->getJson("/api/v1/admin/stok/{$this->kaosL->id}/riwayat")->assertOk()
            ->assertJsonPath('data.0.perubahan', -5)->assertJsonPath('data.0.label', 'Koreksi stok')
            ->assertJsonPath('data.1.perubahan', 10);
    }

    public function test_koreksi_tidak_boleh_di_bawah_stok_yang_dipesan(): void
    {
        Notification::fake();
        $this->order(3); // 3 dari 5 kaos M di-reserve
        $this->sebagaiAdmin();

        $this->postJson("/api/v1/admin/stok/{$this->kaosM->id}", ['jenis' => 'koreksi', 'jumlah' => 2])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '(3)'));
    }

    public function test_dashboard(): void
    {
        Notification::fake();
        $order = $this->order();
        app(UbahStatusOrderAction::class)->execute($order, Order::STATUS_DIBAYAR);
        $this->sebagaiAdmin();

        $this->getJson('/api/v1/admin/dashboard?periode=hari_ini')->assertOk()
            ->assertJsonPath('penjualan.nilai', 220000)
            ->assertJsonPath('order_terbayar', 1)
            ->assertJsonPath('perlu_diproses', 1)
            ->assertJsonCount(1, 'grafik_harian')
            ->assertJsonPath('produk_terlaris.0', ['nama' => 'Kaos Hitam', 'qty' => 2]);
        $this->getJson('/api/v1/admin/dashboard?periode=7_hari')->assertJsonCount(7, 'grafik_harian');
        $this->getJson('/api/v1/admin/dashboard?periode=tahunan')->assertStatus(422);
    }

    public function test_retur_dari_aplikasi(): void
    {
        Notification::fake();
        $order = $this->order(1);
        foreach ([Order::STATUS_DIBAYAR, Order::STATUS_DIPROSES] as $s) {
            app(UbahStatusOrderAction::class)->execute($order->fresh(), $s);
        }
        app(UbahStatusOrderAction::class)->execute($order->fresh(), Order::STATUS_DIKIRIM, null, ['resi' => 'R1']);
        app(UbahStatusOrderAction::class)->execute($order->fresh(), Order::STATUS_SELESAI);
        $retur = app(AjukanReturAction::class)->execute($order->fresh(), 'Jahitan lepas');
        $this->sebagaiAdmin();

        $this->getJson('/api/v1/admin/retur?status=diajukan')->assertOk()->assertJsonPath('data.0.pesanan.nomor', $order->nomor)
            ->assertJsonPath('data.0.aksi.1.kode', 'tolak');
        $this->postJson("/api/v1/admin/retur/{$retur->id}/tolak", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/retur/{$retur->id}/setujui")->assertOk()->assertJsonPath('data.status', 'disetujui');
        $this->postJson("/api/v1/admin/retur/{$retur->id}/selesaikan", ['penyelesaian' => 'refund'])
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('refund.otomatis', false); // dikonfirmasi manual -> refund manual
    }

    public function test_notifikasi_admin_dan_kotak_masuk(): void
    {
        $order = $this->order(4); // stok kaos M: 5 -> tersedia 1 setelah dibayar
        app(UbahStatusOrderAction::class)->execute($order, Order::STATUS_DIBAYAR);

        $jenis = $this->admin->notifications()->get()->pluck('data.jenis')->all();
        $this->assertContains(NotifikasiAdmin::PESANAN_BARU, $jenis);
        $this->assertContains(NotifikasiAdmin::PESANAN_DIBAYAR, $jenis);
        $this->assertContains(NotifikasiAdmin::STOK_MENIPIS, $jenis);
        // Pembeli tidak menerima kabar admin.
        $this->assertSame(0, $this->pembeliA->notifications()->where('type', NotifikasiAdmin::PESANAN_BARU)->count());

        $this->sebagaiAdmin();
        $res = $this->getJson('/api/v1/admin/notifikasi')->assertOk()->assertJsonPath('belum_dibaca', 3);
        $dibayar = collect($res->json('data'))->firstWhere('jenis', NotifikasiAdmin::PESANAN_DIBAYAR);
        $this->assertSame(['jenis' => 'pesanan', 'id' => $order->nomor], $dibayar['tujuan']);
        $this->assertStringContainsString('Rp420.000', $dibayar['isi']); // 4 x 100.000 + ongkir 20.000

        $this->postJson("/api/v1/admin/notifikasi/{$dibayar['id']}/dibaca")->assertOk()->assertJson(['belum_dibaca' => 2]);
        $this->getJson('/api/v1/admin/notifikasi?belum_dibaca=1')->assertJsonCount(2, 'data');
        $this->postJson('/api/v1/admin/notifikasi/dibaca-semua')->assertJson(['belum_dibaca' => 0]);
    }

    public function test_admin_yang_mengonfirmasi_sendiri_tidak_diberi_tahu(): void
    {
        Notification::fake();
        $order = $this->order();
        app(UbahStatusOrderAction::class)->execute($order, Order::STATUS_DIBAYAR, $this->admin, ['catatan' => 'x']);

        Notification::assertNotSentTo($this->admin, KabarAdmin::class, fn (KabarAdmin $n) => $n->jenis === NotifikasiAdmin::PESANAN_DIBAYAR);
        Notification::assertSentTo($this->admin, KabarAdmin::class, fn (KabarAdmin $n) => $n->jenis === NotifikasiAdmin::PESANAN_BARU);
    }

    public function test_push_fcm_dan_token_mati_dihapus(): void
    {
        $kunci = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($kunci, $pem);
        config(['services.fcm.credentials' => base64_encode(json_encode([
            'project_id' => 'toko-uji', 'client_email' => 'fcm@toko-uji.iam.gserviceaccount.com', 'private_key' => $pem,
        ]))]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.uji', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['name' => 'projects/toko-uji/messages/1'])
                ->push(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);

        $this->admin->perangkatAdmin()->create(['fcm_token' => 'hp-aktif']);
        $this->admin->perangkatAdmin()->create(['fcm_token' => 'hp-lama']);

        $this->order(1); // memicu "pesanan baru"

        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/token')
            && $r['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer');
        Http::assertSent(fn ($r) => $r->url() === 'https://fcm.googleapis.com/v1/projects/toko-uji/messages:send'
            && $r->hasHeader('Authorization', 'Bearer ya29.uji')
            && $r['message']['token'] === 'hp-aktif'
            && $r['message']['data']['jenis'] === NotifikasiAdmin::PESANAN_BARU
            && $r['message']['data']['tujuan_jenis'] === 'pesanan');

        $this->assertSame(['hp-aktif'], PerangkatAdmin::pluck('fcm_token')->all());
    }
}
