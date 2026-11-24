<?php

namespace App\Actions\Matches;

use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Throws a category's generated draw away so it can be generated again, e.g.
 * after entries were added, withdrawn or rerouted, or the event's dates
 * changed. Refused once any match has started, so results are never lost.
 */
class ResetDraw
{
    /**
     * @throws InvalidArgumentException when the category has no draw or a match has started
     */
    public function handle(TournamentCategory $tournamentCategory): int
    {
        return DB::transaction(function () use ($tournamentCategory): int {
            if (! $tournamentCategory->hasGeneratedDraw()) {
                throw new InvalidArgumentException('This category has no generated schedule.');
            }

            if ($tournamentCategory->hasStartedMatches()) {
                throw new InvalidArgumentException('The schedule can no longer be regenerated because a match has already started.');
            }

            return $this->deleteMatches($tournamentCategory);
        });
    }

    /**
     * Whether any category of the tournament has a started match.
     */
    public function isBlockedFor(Tournament $tournament): bool
    {
        return $tournament->tournamentCategories->contains(
            fn (TournamentCategory $category): bool => $category->hasStartedMatches()
        );
    }

    /**
     * Clear every generated draw in the tournament.
     *
     * @throws InvalidArgumentException when any match has started
     */
    public function forTournament(Tournament $tournament): int
    {
        return DB::transaction(function () use ($tournament): int {
            $tournament->load('tournamentCategories');

            if ($this->isBlockedFor($tournament)) {
                throw new InvalidArgumentException('A match in this event has already started, so its schedule can\'t be cleared.');
            }

            return $tournament->tournamentCategories->sum(fn (TournamentCategory $category): int => $this->deleteMatches($category));
        });
    }

    private function deleteMatches(TournamentCategory $tournamentCategory): int
    {
        // One at a time so MatchObserver sees each delete.
        return $tournamentCategory->matches()->get()->each(fn (Matches $match) => $match->delete())->count();
    }
}
