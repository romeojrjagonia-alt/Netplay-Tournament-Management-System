<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    /**
     * Mark the email address in a signed verification link as verified.
     *
     * The link is signed and carries a hash of the email it was sent to, so it
     * does not need an active session: players often open it on another device.
     */
    public function __invoke(string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRouteName());
        }

        return redirect()->route('login')
            ->with('status', __('Your email has been verified. You can sign in now.'));
    }
}
