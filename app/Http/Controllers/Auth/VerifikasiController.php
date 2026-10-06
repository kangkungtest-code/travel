<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\VerifikasiEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifikasiController extends Controller
{
    public function pemberitahuan(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('akun');
        }

        return view('toko.auth.verifikasi', ['wajib' => VerifikasiEmail::wajib()]);
    }

    /** Tautan dari email (bertanda tangan). Boleh dibuka di perangkat yang sedang login. */
    public function verifikasi(Request $request, string $id, string $hash): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(hash_equals((string) $user->getKey(), $id), 403);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('akun'))->with('status', __('Your email address is verified. Thank you!'));
    }

    public function kirimUlang(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', __('We sent a new verification link to :email.', ['email' => $request->user()->email]));
    }
}
