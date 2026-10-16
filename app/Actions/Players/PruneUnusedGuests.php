<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes guest player records that were never used: no account, no entry of
 * any kind (even a rejected one counts as an entry) and no link request. A
 * record with history is never touched, so brackets, reports and statistics
 * are unaffected.
 */
class PruneUnusedGuests
{
    public const DEFAULT_DAYS = 7;

    /**
     * @return Builder<Player>
     */
    public function query(int $olderThanDays = self::DEFAULT_DAYS): Builder
    {
        return Player::query()
            ->whereNull('user_id')
            ->whereDoesntHave('applicationPlayers')
            ->whereDoesntHave('linkRequests')
            ->where('created_at', '<', now()->subDays($olderThanDays));
    }

    public function count(int $olderThanDays = self::DEFAULT_DAYS): int
    {
        return $this->query($olderThanDays)->count();
    }

    /**
     * @return int the number of records deleted
     */
    public function handle(int $olderThanDays = self::DEFAULT_DAYS): int
    {
        return $this->query($olderThanDays)->get()->each->delete()->count();
    }
}
