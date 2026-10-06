<?php

namespace Tests\Feature;

use App\Actions\Order\BuatOrderAction;
use App\Actions\Order\UbahStatusOrderAction;
use App\Models\Order;
use App\Models\User;
use App\Notifications\VerifikasiEmail;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Concerns\TokoFixture;
use Tests\TestCase;

class VerifikasiHapusAkunTest extends TestCase
{
    use RefreshDatabase, TokoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanToko();
    }

    private function daftar(): User
    {
        $this->post(route('daftar'), [
            'nama_lengkap' => 'Vera', 'email' => 'vera@contoh.test',
            'password' => 'rahasia-123', 'password_confirmation' => 'rahasia-123',
        ]);

        return User::where('email', 'vera@contoh.test')->firstOrFail();
    }

    public function test_verifikasi_wajib_sebelum_checkout_kalau_smtp_aktif(): void
    {
        Notification::fake();
        config(['toko.wajib_verifikasi_email' => true]);
        $user = $this->daftar();

        Notification::assertSentTo($user, VerifikasiEmail::class);
        $this->assertFalse($user->hasVerifiedEmail());

        $this->get(route('checkout'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('vera@contoh.test');
        $this->get(route('akun'))->assertSee('Resend verification email');

        $this->post(route('verification.send'))->assertSessionHas('status');
        Notification::assertSentToTimes($user, VerifikasiEmail::class, 2);

        // Tautan palsu ditolak, tautan asli memverifikasi.
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('lain@contoh.test')]))->assertForbidden();
        $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden(); // tanpa tanda tangan
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertRedirect(route('akun'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->assertNotSame(route('verification.notice'), $this->get(route('checkout'))->headers->get('Location'));
    }

    public function test_tanpa_smtp_verifikasi_tidak_menghalangi(): void
    {
        Notification::fake();
        config(['toko.wajib_verifikasi_email' => null, 'mail.default' => 'log']);
        $this->daftar();

        // Keranjang kosong -> dialihkan ke keranjang, bukan ke halaman verifikasi.
        $this->assertNotSame(route('verification.notice'), $this->get(route('checkout'))->headers->get('Location'));
    }

    public function test_hapus_akun_dengan_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'rahasia-123']);
        $this->alamat($user);
        $user->cart()->firstOrCreate()->items()->create(['variant_id' => $this->kaosM->id, 'qty' => 2]);
        $order = app(BuatOrderAction::class)->execute($user, $user->addresses()->first(), 'IDR'); // belum dibayar
        $user->cart()->firstOrCreate()->items()->create(['variant_id' => $this->kaosL->id, 'qty' => 1]);

        $this->actingAs($user, 'web');
        $this->get(route('akun'))->assertSee('Delete account');

        $this->delete(route('akun.hapus'), ['konfirmasi_password' => 'salah'])->assertSessionHasErrorsIn('hapus', 'konfirmasi_password');
        $this->assertNull($user->fresh()->dihapus_pada);

        $this->delete(route('akun.hapus'), ['konfirmasi_password' => 'rahasia-123'])->assertRedirect(route('home'));
        $this->assertGuest('web');

        $u = $user->fresh();
        $this->assertNotNull($u->dihapus_pada);
        $this->assertSame('Akun dihapus', $u->nama_lengkap);
        $this->assertStringEndsWith('@akun-dihapus.invalid', $u->email);
        $this->assertSame(0, $u->addresses()->count());
        $this->assertNull($u->cart);
        // Pesanan lama tetap ada (pembukuan); yang belum dibayar dibatalkan & stoknya kembali.
        $this->assertSame(Order::STATUS_DIBATALKAN, $order->fresh()->status);
        $this->assertSame(0, $this->kaosM->fresh()->stocks->first()->jumlah_reserved);

        // Email lama tidak bisa dipakai masuk, tapi bisa dipakai daftar lagi.
        $this->post(route('login'), ['email' => $user->email, 'password' => 'rahasia-123'])->assertSessionHasErrors('email');
    }

    public function test_hapus_akun_ditolak_kalau_pesanan_berjalan_atau_akun_staf(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'rahasia-123']);
        $user->cart()->firstOrCreate()->items()->create(['variant_id' => $this->kaosM->id, 'qty' => 1]);
        $order = app(BuatOrderAction::class)->execute($user, $this->alamat($user), 'IDR');
        app(UbahStatusOrderAction::class)->execute($order, Order::STATUS_DIBAYAR);

        $this->actingAs($user, 'web')->delete(route('akun.hapus'), ['konfirmasi_password' => 'rahasia-123'])
            ->assertSessionHasErrorsIn('hapus', 'hapus');
        $this->assertNull($user->fresh()->dihapus_pada);

        $this->seed(RoleAndPermissionSeeder::class);
        $staf = User::factory()->create(['password' => 'rahasia-123']);
        $staf->assignRole('Owner');
        $this->actingAs($staf, 'web')->delete(route('akun.hapus'), ['konfirmasi_password' => 'rahasia-123'])
            ->assertSessionHasErrorsIn('hapus', 'hapus');
    }

    public function test_akun_login_sosial_konfirmasi_dengan_email(): void
    {
        $user = User::factory()->create(['email' => 'sosial@contoh.test']);
        $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'g-1']);

        $this->actingAs($user, 'web');
        $this->get(route('akun'))->assertSee('Type your email address to confirm');
        $this->delete(route('akun.hapus'), ['konfirmasi_email' => 'lain@contoh.test'])->assertSessionHasErrorsIn('hapus', 'konfirmasi_email');
        $this->delete(route('akun.hapus'), ['konfirmasi_email' => 'SOSIAL@contoh.test'])->assertRedirect(route('home'));
        $this->assertSame(0, $user->socialAccounts()->count());
    }
}
