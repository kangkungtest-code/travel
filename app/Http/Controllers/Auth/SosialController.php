<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Oauth\ProviderSosial;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Masuk / daftar lewat Google atau LINE. */
class SosialController extends Controller
{
    private const SESI_TERTUNDA = 'sosial_tertunda';

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $p = ProviderSosial::cari($provider) ?? abort(404);
        \App\Support\Kembali::simpan($request);
        $state = Str::random(40);
        $request->session()->put('oauth_state', $state);

        return redirect()->away($p->urlRedirect($state));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $p = ProviderSosial::cari($provider) ?? abort(404);
        $state = $request->session()->pull('oauth_state');

        if (! $state || ! hash_equals($state, (string) $request->query('state')) || ! $request->filled('code')) {
            return redirect()->route('login')->withErrors(['email' => __('Sign-in with :provider was cancelled or expired. Please try again.', ['provider' => $p->nama()])]);
        }

        try {
            $profil = $p->profil($request->query('code'));
        } catch (Throwable $e) {
            Log::warning("Login {$provider} gagal: ".$e->getMessage());

            return redirect()->route('login')->withErrors(['email' => __('We couldn\'t sign you in with :provider. Please try again.', ['provider' => $p->nama()])]);
        }

        $akun = SocialAccount::query()->where('provider', $provider)->where('provider_user_id', $profil['id'])->first();
        if ($akun) {
            return $this->loginSebagai($request, $akun->user);
        }

        if ($profil['email'] && $profil['email_terverifikasi']) {
            $user = DB::transaction(function () use ($profil, $provider) {
                $user = User::query()->where('email', $profil['email'])->first() ?? $this->buatUser($profil['nama'], $profil['email'], true);
                $user->socialAccounts()->create(['provider' => $provider, 'provider_user_id' => $profil['id'], 'email' => $profil['email']]);

                return $user;
            });

            return $this->loginSebagai($request, $user);
        }

        // Provider tidak membagikan email: minta pembeli mengisinya.
        $request->session()->put(self::SESI_TERTUNDA, ['provider' => $provider, 'id' => $profil['id'], 'nama' => $profil['nama']]);

        return redirect()->route('masuk.sosial.email');
    }

    public function formEmail(Request $request): View|RedirectResponse
    {
        $tertunda = $request->session()->get(self::SESI_TERTUNDA) ?? null;
        if (! $tertunda) {
            return redirect()->route('login');
        }

        return view('toko.auth.lengkapi-email', ['provider' => ProviderSosial::semua()[$tertunda['provider']]->nama(), 'nama' => $tertunda['nama']]);
    }

    public function simpanEmail(Request $request): RedirectResponse
    {
        $tertunda = $request->session()->get(self::SESI_TERTUNDA) ?? abort(404);

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
        ], [
            'email.unique' => __('This email is already registered. Sign in with your email and password instead.'),
        ]);

        $user = DB::transaction(function () use ($data, $tertunda) {
            $user = $this->buatUser($data['nama_lengkap'], $data['email'], false);
            $user->socialAccounts()->create(['provider' => $tertunda['provider'], 'provider_user_id' => $tertunda['id']]);

            return $user;
        });

        $request->session()->forget(self::SESI_TERTUNDA);

        return $this->loginSebagai($request, $user);
    }

    private function buatUser(?string $nama, string $email, bool $terverifikasi): User
    {
        $user = User::create([
            'nama_lengkap' => $nama ?: Str::before($email, '@'),
            'email' => $email,
            // Password acak; pembeli bisa membuat password sendiri lewat "Lupa password".
            'password' => Str::random(40),
            'bahasa_preferensi' => app()->getLocale(),
            'mata_uang_preferensi' => app('toko.currency'),
        ]);

        if ($terverifikasi) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    private function loginSebagai(Request $request, User $user): RedirectResponse
    {
        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('akun'));
    }
}
