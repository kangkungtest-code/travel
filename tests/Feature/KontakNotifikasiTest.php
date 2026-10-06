<?php

namespace Tests\Feature;

use App\Actions\Order\BuatOrderAction;
use App\Actions\Order\UbahStatusOrderAction;
use App\Filament\Pages\KontakNotifikasi;
use App\Models\Order;
use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\User;
use App\Notifications\EmailPemilik;
use App\Support\EmailPemilik as Pemilik;
use App\Support\KontakAdmin;
use App\Support\NotifikasiAdmin;
use Database\Seeders\RoleAndPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Concerns\TokoFixture;
use Tests\TestCase;

class KontakNotifikasiTest extends TestCase
{
    use RefreshDatabase, TokoFixture;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->siapkanToko();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Owner');
        Filament::setCurrentPanel('admin');
    }

    public function test_admin_mengisi_kontak_medsos_dan_email_pemilik(): void
    {
        $this->actingAs($this->admin, 'admin');
        $this->get(KontakNotifikasi::getUrl())->assertOk()->assertSee('Media sosial');

        Livewire::test(KontakNotifikasi::class)
            ->fillForm([
                'wa' => '0812 1111 2222', 'line' => '@tokokami', 'email' => 'Halo@Toko.test', 'telepon' => '021 555 1234',
                'medsos_instagram' => '@kangkung.apparel', 'medsos_shopee' => 'https://shopee.co.id/kangkung',
                'medsos_tiktok' => 'bukan akun!',
                'email_pemilik' => "pemilik@toko.test, gudang@toko.test\nsalah-alamat",
            ])
            ->call('simpan')
            ->assertHasFormErrors(['medsos_tiktok', 'email_pemilik']);

        Livewire::test(KontakNotifikasi::class)
            ->fillForm([
                'wa' => '0812 1111 2222', 'line' => '@tokokami', 'email' => 'Halo@Toko.test', 'telepon' => '021 555 1234',
                'medsos_instagram' => '@kangkung.apparel', 'medsos_shopee' => 'https://shopee.co.id/kangkung', 'medsos_tiktok' => null,
                'email_pemilik' => "pemilik@toko.test, gudang@toko.test",
                'email_jenis' => [NotifikasiAdmin::PESANAN_DIBAYAR, NotifikasiAdmin::PESANAN_BARU],
            ])
            ->call('simpan')
            ->assertHasNoFormErrors()
            ->assertNotified('Tersimpan');

        $this->assertSame('6281211112222', Pengaturan::ambil('kontak.wa'));
        $this->assertSame('halo@toko.test', Pengaturan::ambil('kontak.email'));
        $this->assertSame('tel:+62215551234', KontakAdmin::urlTelepon());
        $this->assertSame(['pemilik@toko.test', 'gudang@toko.test'], Pemilik::penerima());
        $this->assertTrue(Pemilik::aktif(NotifikasiAdmin::PESANAN_BARU));
        $this->assertFalse(Pemilik::aktif(NotifikasiAdmin::RETUR_DIAJUKAN));
        $this->assertSame([
            ['jenis' => 'instagram', 'label' => 'Instagram', 'url' => 'https://instagram.com/kangkung.apparel'],
            ['jenis' => 'shopee', 'label' => 'Shopee', 'url' => 'https://shopee.co.id/kangkung'],
        ], KontakAdmin::medsos());

        // Footer & FAQ menampilkan medsos dan semua tombol kontak.
        $this->get(route('faq'))
            ->assertSee('https://instagram.com/kangkung.apparel', false)
            ->assertSee('mailto:halo@toko.test', false)
            ->assertSee('Email us')->assertSee('Call us');
        $this->postJson('/chatbot', ['pesan' => 'mau ngobrol sama admin'])
            ->assertJsonPath('kontak.2.jenis', 'email')->assertJsonPath('kontak.3.jenis', 'telepon');
    }

    public function test_halaman_hanya_untuk_izin_pengaturan(): void
    {
        $staf = User::factory()->create();
        Role::findOrCreate('Gudang', 'admin')->syncPermissions(['stok.edit']);
        $staf->assignRole('Gudang');
        $this->actingAs($staf, 'admin')->get(KontakNotifikasi::getUrl())->assertForbidden();
    }

    private function order(): Order
    {
        $u = $this->pembeli();
        $u->cart()->firstOrCreate()->items()->create(['variant_id' => $this->kaosM->id, 'qty' => 2]);

        return app(BuatOrderAction::class)->execute($u, $this->alamat($u), 'IDR');
    }

    public function test_email_pemilik_saat_pesanan_dibayar(): void
    {
        Notification::fake();
        Pengaturan::simpan('email_pemilik.alamat', 'pemilik@toko.test');

        $order = $this->order();
        // Default: pesanan baru tidak di-email, pesanan dibayar di-email.
        Notification::assertNothingSentTo(new AnonymousNotifiable, EmailPemilik::class);
        Notification::assertSentOnDemandTimes(EmailPemilik::class, 0);

        app(UbahStatusOrderAction::class)->execute($order, Order::STATUS_DIBAYAR);

        Notification::assertSentOnDemand(EmailPemilik::class, function (EmailPemilik $n, array $channels, object $notifiable) use ($order) {
            $mail = $n->toMail($notifiable);
            $teks = implode("\n", $mail->introLines);

            return $notifiable->routes['mail'] === ['pemilik@toko.test']
                && $n->jenis === NotifikasiAdmin::PESANAN_DIBAYAR
                && str_contains($mail->subject, $order->nomor)
                && str_contains($teks, 'Kaos Hitam')
                && str_contains($teks, 'Rp220.000')
                && str_contains($mail->actionUrl, '/admin/orders/');
        });
    }

    public function test_tanpa_penerima_atau_jenis_dimatikan_tidak_ada_email(): void
    {
        Notification::fake();
        $order = $this->order();
        app(UbahStatusOrderAction::class)->execute($order, Order::STATUS_DIBAYAR);
        Notification::assertSentOnDemandTimes(EmailPemilik::class, 0);

        Pengaturan::simpan('email_pemilik.alamat', 'pemilik@toko.test');
        Pengaturan::simpan('email_pemilik.'.NotifikasiAdmin::PESANAN_DIBAYAR, '0');
        $lain = $this->order();
        app(UbahStatusOrderAction::class)->execute($lain, Order::STATUS_DIBAYAR);
        Notification::assertSentOnDemandTimes(EmailPemilik::class, 0);
    }
}
