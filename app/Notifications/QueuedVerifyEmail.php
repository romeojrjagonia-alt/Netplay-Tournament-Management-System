<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The stock verification email, delivered through the queue so a slow mail
 * provider never blocks the request that triggered it.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the notification may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying a failed delivery.
     */
    public function backoff(): int
    {
        return 10;
    }
}
