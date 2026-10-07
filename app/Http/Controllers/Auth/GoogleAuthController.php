<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Send the browser to Google's consent screen.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google's callback: log in a known user, link a matching
     * email/password account, or start profile setup for a new one.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->failed('Google sign-in was cancelled or expired. Please try again.');
        }

        $email = $googleUser->getEmail();

        if (! $email || ! ($googleUser->user['email_verified'] ?? false)) {
            return $this->failed('Your Google account does not have a verified email address.');
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user === null) {
            Session::put('google_signup', [
                'id' => $googleUser->getId(),
                'email' => $email,
                'first_name' => $googleUser->user['given_name'] ?? (string) $googleUser->getName(),
                'last_name' => $googleUser->user['family_name'] ?? '',
            ]);

            return redirect()->route('register');
        }

        // Google has verified this email, so linking and verifying is safe.
        $user->forceFill([
            'google_id' => $user->google_id ?? $googleUser->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user, remember: true);
        Session::regenerate();
        Session::forget('google_signup');

        return redirect()->intended(route($user->homeRouteName(), absolute: false));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
