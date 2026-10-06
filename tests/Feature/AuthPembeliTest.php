<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthPembeliTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_lalu_masuk_ke_akun(): void
    {
        $this->withSession(['locale' => 'id', 'currency' => 'IDR'])
            ->post('/daftar', [
                'nama_lengkap' => 'Chen Wei',
                'email' => 'chen@example.tw',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ])->assertRedirect(route('akun'));

        $user = User::where('email', 'chen@example.tw')->firstOrFail();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('id', $user->bahasa_preferensi);
        $this->assertSame('IDR', $user->mata_uang_preferensi);
        $this->get('/akun')->assertOk()->assertSee('Chen');
    }

    public function test_validasi_daftar(): void
    {
        User::factory()->create(['email' => 'ada@toko.test']);

        $this->post('/daftar', ['nama_lengkap' => '', 'email' => 'ada@toko.test', 'password' => '123', 'password_confirmation' => '456'])
            ->assertSessionHasErrors(['nama_lengkap', 'email', 'password']);
    }

    public function test_masuk_dan_keluar(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->post('/masuk', ['email' => $user->email, 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest('web');

        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123'])->assertRedirect(route('akun'));
        $this->assertAuthenticatedAs($user, 'web');

        $this->post('/keluar')->assertRedirect(route('home'));
        $this->assertGuest('web');
    }

    public function test_masuk_dibatasi_setelah_5_kali_gagal(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        foreach (range(1, 5) as $_) {
            $this->post('/masuk', ['email' => $user->email, 'password' => 'salah']);
        }

        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_halaman_akun_wajib_login(): void
    {
        $this->get('/akun')->assertRedirect(route('login'));
        $this->get('/checkout')->assertRedirect(route('login'));
    }

    public function test_login_customer_tidak_memberi_akses_admin(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);
        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123']);

        $this->get('/admin')->assertRedirect(); // ke halaman login admin, sesi customer tidak dipakai
        $this->assertGuest('admin');
    }

    public function test_lupa_dan_reset_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/lupa-password', ['email' => $user->email])->assertSessionHas('status');
        $this->post('/lupa-password', ['email' => 'tidak-ada@toko.test'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1',
        ])->assertRedirect(route('login'));

        $this->post('/masuk', ['email' => $user->email, 'password' => 'passwordbaru1']);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_ubah_profil_dan_password(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);
        $this->actingAs($user, 'web');

        $this->put('/akun/profil', ['nama_lengkap' => 'Nama Baru', 'bahasa_preferensi' => 'zh_TW', 'mata_uang_preferensi' => 'TWD'])
            ->assertSessionHas('status');
        $this->assertSame('zh_TW', $user->fresh()->bahasa_preferensi);

        $this->put('/akun/password', ['password_lama' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHasErrorsIn('password', 'password_lama');
        $this->put('/akun/password', ['password_lama' => 'rahasia123', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHas('status');
    }

    public function test_tombol_login_sosial_tersembunyi_kalau_belum_dikonfigurasi(): void
    {
        config(['services.google.client_id' => null, 'services.line.client_id' => null]);
        $this->get('/masuk')->assertOk()->assertDontSee('Continue with Google');
        $this->get('/masuk/google')->assertNotFound();

        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'rahasia']);
        $this->get('/masuk')->assertSee('Continue with Google')->assertDontSee('Continue with LINE');
    }

    public function test_popup_masuk_kembali_ke_halaman_asal(): void
    {
        $user = User::factory()->create(['email' => 'pop@contoh.test', 'password' => 'rahasia-123']);

        // Halaman toko untuk tamu memuat pop-up; tombol header membukanya.
        $this->get(route('faq'))->assertSee('data-dialog-masuk', false)->assertSee('data-masuk', false);

        // Salah password: kembali ke halaman asal, error di bag "dialog" -> pop-up terbuka lagi.
        // (Cek lewat halaman: assertSessionHasErrors menghapus error dari session JSON.)
        $this->from(route('faq'))->post(route('login'), ['_dialog' => 'masuk', 'kembali' => '/faq', 'email' => 'pop@contoh.test', 'password' => 'salah'])
            ->assertRedirect(route('faq'));
        $this->get(route('faq'))->assertSee('data-buka-awal="masuk"', false)->assertSee('Email or password is incorrect.');

        $this->post(route('login'), ['_dialog' => 'masuk', 'kembali' => '/faq?x=1', 'email' => 'pop@contoh.test', 'password' => 'rahasia-123'])
            ->assertRedirect(url('/faq?x=1'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_popup_daftar_dan_tujuan_luar_ditolak(): void
    {
        $this->post(route('daftar'), [
            '_dialog' => 'daftar', 'kembali' => '//jahat.example/phish',
            'nama_lengkap' => 'Popi', 'email' => 'popi@contoh.test', 'password' => 'rahasia-123', 'password_confirmation' => 'rahasia-123',
        ])->assertRedirect(route('akun'));

        auth('web')->logout();
        $this->from('/produk')->post(route('daftar'), ['_dialog' => 'daftar', 'nama_lengkap' => '', 'email' => 'x'])->assertRedirect('/produk');
        $this->get('/produk')->assertSee('data-buka-awal="daftar"', false)->assertSee('id="e-dd-nama_lengkap"', false);
    }
}
