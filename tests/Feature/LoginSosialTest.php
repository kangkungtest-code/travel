<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginSosialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google' => ['client_id' => 'gid', 'client_secret' => 'gsecret'],
            'services.line' => ['client_id' => 'lid', 'client_secret' => 'lsecret'],
        ]);
    }

    private function mulai(string $provider): string
    {
        $res = $this->get("/masuk/{$provider}")->assertRedirect();
        parse_str(parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame(route('masuk.sosial.callback', $provider), $q['redirect_uri']);

        return $q['state'];
    }

    public function test_google_membuat_akun_baru(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok', 'id_token' => 'x']),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'g-123', 'email' => 'budi@gmail.com', 'email_verified' => true, 'name' => 'Budi']),
        ]);

        $state = $this->mulai('google');
        $this->get("/masuk/google/callback?state={$state}&code=abc")->assertRedirect(route('akun'));

        $user = User::where('email', 'budi@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->socialAccounts()->where('provider', 'google')->where('provider_user_id', 'g-123')->exists());
    }

    public function test_google_menyambung_ke_akun_email_yang_sudah_ada(): void
    {
        $ada = User::factory()->create(['email' => 'budi@gmail.com']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'g-123', 'email' => 'budi@gmail.com', 'email_verified' => true, 'name' => 'Budi']),
        ]);

        $state = $this->mulai('google');
        $this->get("/masuk/google/callback?state={$state}&code=abc");

        $this->assertAuthenticatedAs($ada, 'web');
        $this->assertSame(1, User::count());
    }

    public function test_state_salah_ditolak(): void
    {
        $this->mulai('google');
        $this->get('/masuk/google/callback?state=palsu&code=abc')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_line_tanpa_email_minta_isi_email(): void
    {
        Http::fake([
            'api.line.me/oauth2/v2.1/token' => Http::response(['access_token' => 'tok', 'id_token' => 'idt']),
            'api.line.me/oauth2/v2.1/verify' => Http::response(['sub' => 'U999', 'name' => 'Chen']),
        ]);

        $state = $this->mulai('line');
        $this->get("/masuk/line/callback?state={$state}&code=abc")->assertRedirect(route('masuk.sosial.email'));
        $this->get('/masuk/lengkapi-email')->assertOk()->assertSee('Chen');

        $this->post('/masuk/lengkapi-email', ['nama_lengkap' => 'Chen Wei', 'email' => 'chen@example.tw'])->assertRedirect(route('akun'));

        $user = User::where('email', 'chen@example.tw')->firstOrFail();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertTrue(SocialAccount::where('provider', 'line')->where('provider_user_id', 'U999')->where('user_id', $user->id)->exists());

        // Login berikutnya langsung masuk lewat akun LINE yang sama.
        $this->post('/keluar');
        $state = $this->mulai('line');
        $this->get("/masuk/line/callback?state={$state}&code=abc")->assertRedirect(route('akun'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_provider_error_ditangani(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $state = $this->mulai('google');
        $this->get("/masuk/google/callback?state={$state}&code=abc")->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }
}
