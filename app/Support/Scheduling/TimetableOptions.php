<?php

namespace App\Support\Scheduling;

/**
 * What the admin chose on the generate screen for auto-dating a category's
 * matches: how long each match takes, the gap after it, and how many courts
 * can run at once.
 */
final readonly class TimetableOptions
{
    public function __construct(
        public int $durationMinutes = 45,
        public int $breakMinutes = 0,
        public int $courts = 1,
    ) {}
}
