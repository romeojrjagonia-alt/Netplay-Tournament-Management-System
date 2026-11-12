<?php

namespace App\Support\Officials;

use App\Models\Matches;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\User;

/**
 * Nobody may be both a competitor and an official in the same tournament.
 * An umpire who also plays (they can switch to the player side) is kept out
 * of a tournament's draw while they officiate in it, and kept off its matches
 * while they are entered in it.
 */
class OfficialConflicts
{
    /**
     * Whether the user is assigned as umpire to any match of the tournament.
     */
    public static function officiatesIn(User $user, Tournament|int $tournament): bool
    {
        return Matches::query()
            ->where('assigned_umpire_id', $user->getKey())
            ->whereHas('tournamentCategory', fn ($query) => $query->where('tournament_id', self::tournamentId($tournament)))
            ->exists();
    }

    /**
     * Whether the user's own player record is in the tournament's draw (an
     * application that has not been rejected), alone or as a doubles partner.
     */
    public static function isEnteredIn(User $user, Tournament|int $tournament): bool
    {
        return PlayerApplication::query()
            ->where('status', '!=', 'rejected')
            ->whereHas('tournamentCategory', fn ($query) => $query->where('tournament_id', self::tournamentId($tournament)))
            ->whereHas('applicationPlayers.player', fn ($query) => $query->where('user_id', $user->getKey()))
            ->exists();
    }

    /**
     * The ids of the umpires who are entered in the tournament as players.
     *
     * @return list<int>
     */
    public static function enteredUmpireIds(Tournament|int $tournament): array
    {
        return User::query()
            ->where('role', 'umpire')
            ->whereHas('player', fn ($query) => $query->whereHas('applicationPlayers', fn ($query) => $query
                ->whereHas('application', fn ($query) => $query
                    ->where('status', '!=', 'rejected')
                    ->whereHas('tournamentCategory', fn ($query) => $query->where('tournament_id', self::tournamentId($tournament))))))
            ->pluck('id')
            ->all();
    }

    public static function officiatingMessage(): string
    {
        return 'You are assigned as an umpire in this tournament, so you cannot enter it as a player.';
    }

    public static function partnerOfficiatingMessage(): string
    {
        return 'This player is assigned as an umpire in this tournament, so they cannot be entered as a player.';
    }

    public static function enteredMessage(string $umpireName): string
    {
        return "{$umpireName} is entered in this tournament as a player, so they cannot also be its umpire.";
    }

    private static function tournamentId(Tournament|int $tournament): int
    {
        return $tournament instanceof Tournament ? $tournament->getKey() : $tournament;
    }
}
