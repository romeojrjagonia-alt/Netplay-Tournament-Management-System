<?php

namespace App\Support\Scheduling;

use InvalidArgumentException;

/**
 * The event's dates, days and time window have fewer match slots than the
 * draw needs.
 */
class InsufficientSlotsException extends InvalidArgumentException
{
    public function __construct(public readonly int $unplaced, public readonly int $total)
    {
        parent::__construct(
            'Only '.($total - $unplaced)." of {$total} matches fit within the event's dates, days and time window. "
            .'Shorten the match length, add courts, or unpublish the event and widen its dates, days or time window.'
        );
    }
}
