<?php

namespace App\Livewire\Actions;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

class SendVerificationEmail
{
    public const SENT = 'sent';

    public const ALREADY_VERIFIED = 'already-verified';

    public const THROTTLED = 'throttled';

    /**
     * Send the user a verification link, unless they are already verified or
     * have requested too many links in the last minute.
     *
     * @return self::SENT|self::ALREADY_VERIFIED|self::THROTTLED
     */
    public function __invoke(User $user): string
    {
        if ($user->hasVerifiedEmail()) {
            return self::ALREADY_VERIFIED;
        }

        $sent = RateLimiter::attempt(
            "send-verification-email:{$user->id}",
            maxAttempts: 2,
            callback: fn () => $user->sendEmailVerificationNotification(),
            decaySeconds: 60,
        );

        return $sent ? self::SENT : self::THROTTLED;
    }
}
