<?php

namespace App\Support\Players;

use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Finds the guest players (no account) that were registered under a signed-in
 * player's verified email, so they can claim that history (NETPLAY.md 4.2).
 * Unverified emails never match: anyone can type any address at sign-up.
 */
class GuestPlayerMatcher
{
    /**
     * Guest records the user has not yet claimed, declined, or had rejected.
     *
     * @return Collection<int, Player>
     */
    public function candidatesFor(User $user): Collection
    {
        if (! $user->isPlayer() || ! $user->hasVerifiedEmail()) {
            return collect();
        }

        return Player::query()
            ->whereNull('user_id')
            ->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])
            ->whereNotIn('id', $user->playerLinkRequests()->select('player_id'))
            ->with(['applicationPlayers.application.tournamentCategory.tournament'])
            ->orderBy('id')
            ->get();
    }
}
