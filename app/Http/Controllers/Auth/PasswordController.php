<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as AturanPassword;

/** Lupa password & reset lewat email (broker bawaan Laravel). */
class PasswordController extends Controller
{
    public function formLupa(): View
    {
        return view('toko.auth.lupa-password');
    }

    public function kirimLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('users')->sendResetLink($request->only('email'));

        // Pesan sama untuk email terdaftar maupun tidak, supaya tidak bisa dipakai menebak akun.
        return back()->with('status', __('If that email is registered, we have sent a link to reset your password.'));
    }

    public function formReset(Request $request, string $token): View
    {
        return view('toko.auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', AturanPassword::defaults()],
        ]);

        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __('Your password has been reset. You can sign in now.'))
            : back()->withInput($request->only('email'))->withErrors(['email' => __('This reset link is invalid or has expired.')]);
    }
}
