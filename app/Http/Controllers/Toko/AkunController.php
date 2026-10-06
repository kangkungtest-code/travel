<?php

namespace App\Http\Controllers\Toko;

use App\Http\Controllers\Controller;
use App\Support\TampilanOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AkunController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('toko.akun.index', [
            'user' => $user,
            'pesanan' => $user->orders()->latest()->take(5)->get()->map(fn ($o) => TampilanOrder::ringkas($o)),
            'alamat' => $user->addresses()->orderByDesc('is_default')->oldest()->get(),
            'punyaSosial' => $user->socialAccounts()->pluck('provider')->all(),
            'perluVerifikasi' => ! $user->hasVerifiedEmail(),
        ]);
    }

    public function updateProfil(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:120'],
            'bahasa_preferensi' => ['required', Rule::in(array_keys(config('toko.locales')))],
            'mata_uang_preferensi' => ['required', Rule::in(config('toko.currencies'))],
        ]);

        $request->user()->update($data);
        $request->session()->put(['locale' => $data['bahasa_preferensi'], 'currency' => $data['mata_uang_preferensi']]);

        return back()->with('status', __('Profile saved.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'password_lama' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', __('Password changed.'));
    }

    /**
     * Hapus akun: konfirmasi dengan password, atau (akun login Google/LINE yang mungkin
     * tidak tahu password-nya) dengan mengetik ulang alamat email.
     */
    public function hapus(Request $request, \App\Actions\Akun\HapusAkunAction $hapus): RedirectResponse
    {
        $user = $request->user();
        $sosial = $user->socialAccounts()->exists();

        $request->validateWithBag('hapus', $sosial
            ? ['konfirmasi_email' => ['required', 'string', function ($attr, $value, $fail) use ($user) {
                if (mb_strtolower(trim($value)) !== mb_strtolower($user->email)) {
                    $fail(__('Type the email address of this account exactly.'));
                }
            }]]
            : ['konfirmasi_password' => ['required', 'current_password:web']]);

        try {
            $hapus->execute($user);
        } catch (\App\Exceptions\TokoException $e) {
            return back()->withErrors(['hapus' => $e->getMessage()], 'hapus');
        }

        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('Your account has been deleted.'));
    }
}
