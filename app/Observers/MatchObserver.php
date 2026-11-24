<?php

namespace App\Observers;

use App\Models\Matches;
use App\Models\PlayerApplication;
use App\Models\PlayerStatistic;
use App\Support\Scheduling\SingleEliminationScheduler;
use App\Support\Standings\RoundRobinStandings;

class MatchObserver
{
    /**
     * The player level ladder, lowest to highest. A championship win moves
     * the winning entry's players one rung up; Unranked (null) starts here.
     *
     * @var list<string>
     */
    private const LEVEL_LADDER = [
        'Beginner',
        'Intermediate',
        'Level G',
        'Level F',
        'Level E',
        'Level D',
        'Level C',
        'Level B',
        'Level A',
    ];

    /**
     * Handle the Matches "created" event.
     */
    public function created(Matches $matches): void
    {
        if ($matches->status === 'completed' && $matches->winner_application_id) {
            $this->adjustStatistics($matches, $matches->winner_application_id, 1);
            $this->promoteChampionshipWinners($matches, $matches->winner_application_id);
        }
    }

    /**
     * Handle the Matches "updated" event.
     *
     * Keeps `player_statistics` in sync as a match is marked completed,
     * reverted from completed, or its winner is corrected.
     */
    public function updated(Matches $matches): void
    {
        if (! $matches->wasChanged('status') && ! $matches->wasChanged('winner_application_id')) {
            return;
        }

        $wasCompleted = $matches->getOriginal('status') === 'completed';
        $isCompleted = $matches->status === 'completed';
        $originalWinnerId = $matches->getOriginal('winner_application_id');

        if ($wasCompleted && $originalWinnerId && (! $isCompleted || $matches->wasChanged('winner_application_id'))) {
            $this->adjustStatistics($matches, $originalWinnerId, -1);
        }

        if ($isCompleted && $matches->winner_application_id && (! $wasCompleted || $matches->wasChanged('winner_application_id'))) {
            app(SingleEliminationScheduler::class)->advance($matches);
            $this->adjustStatistics($matches, $matches->winner_application_id, 1);
            $this->promoteChampionshipWinners($matches, $matches->winner_application_id);
        }
    }

    /**
     * Handle the Matches "deleted" event.
     */
    public function deleted(Matches $matches): void
    {
        if ($matches->status === 'completed' && $matches->winner_application_id) {
            $this->adjustStatistics($matches, $matches->winner_application_id, -1);
        }
    }

    /**
     * Apply (or reverse, with $direction of -1) a completed match's result
     * to the `player_statistics` row of every player in both entries.
     */
    private function adjustStatistics(Matches $matches, int $winnerApplicationId, int $direction): void
    {
        $matches->loadMissing('matchApplications.application.applicationPlayers');

        // A bye has a lone entry and no opponent, so it is not a played match.
        // Neither is a walkover: nobody played, so no wins or losses are credited.
        if ($matches->matchApplications->count() < 2 || $matches->isWalkover()) {
            return;
        }

        foreach ($matches->matchApplications as $matchApplication) {
            $application = $matchApplication->application;

            if (! $application) {
                continue;
            }

            $won = $application->id === $winnerApplicationId;

            foreach ($application->applicationPlayers as $applicationPlayer) {
                $statistic = PlayerStatistic::firstOrCreate(
                    ['player_id' => $applicationPlayer->player_id],
                    ['matches_played' => 0, 'wins' => 0, 'losses' => 0]
                );

                $statistic->increment('matches_played', $direction);
                $statistic->increment($won ? 'wins' : 'losses', $direction);
            }
        }
    }

    /**
     * Advance every player on the winning entry one rung up the level
     * ladder, but only when this match is its tournament category's
     * championship (the highest round number among all of its matches).
     *
     * Promotion is deliberately one-directional: correcting or deleting a
     * match does not demote a player, since a level is treated as earned
     * rather than a value to be reversed alongside the match record.
     */
    private function promoteChampionshipWinners(Matches $matches, int $winnerApplicationId): void
    {
        $matches->loadMissing('tournamentCategory.tournament');

        // A round robin has no single final match; its champion is decided
        // from the standings once every match is done.
        if ($matches->tournamentCategory->tournament->format === 'round_robin') {
            $this->promoteRoundRobinChampion($matches);

            return;
        }

        $finalRound = Matches::where('tournament_category_id', $matches->tournament_category_id)->max('round');

        if ((int) $finalRound !== $matches->round) {
            return;
        }

        $matches->loadMissing('matchApplications.application.applicationPlayers.player');

        $winningApplication = $matches->matchApplications
            ->first(fn ($matchApplication) => $matchApplication->application_id === $winnerApplicationId)
            ?->application;

        if (! $winningApplication) {
            return;
        }

        $this->promotePlayers($winningApplication);
    }

    /**
     * Once every match in a round-robin category is completed, promote the
     * standings leader. An unresolved tie for first promotes no one.
     */
    private function promoteRoundRobinChampion(Matches $matches): void
    {
        $hasUnfinishedMatches = Matches::where('tournament_category_id', $matches->tournament_category_id)
            ->where('status', '!=', 'completed')
            ->exists();

        if ($hasUnfinishedMatches) {
            return;
        }

        $champion = app(RoundRobinStandings::class)->champion($matches->tournamentCategory);

        if ($champion) {
            $this->promotePlayers($champion);
        }
    }

    private function promotePlayers(PlayerApplication $winningApplication): void
    {
        $winningApplication->loadMissing('applicationPlayers.player');

        foreach ($winningApplication->applicationPlayers as $applicationPlayer) {
            $player = $applicationPlayer->player;

            if (! $player) {
                continue;
            }

            $nextLevel = $this->nextLevel($player->level);

            if ($nextLevel !== null && $nextLevel !== $player->level) {
                $player->update(['level' => $nextLevel]);
            }
        }
    }

    /**
     * The level one rung above `$currentLevel`, or the same level if it's
     * already at the top of the ladder. Null (Unranked) starts at the
     * bottom rung. An unrecognized level is left untouched.
     */
    private function nextLevel(?string $currentLevel): ?string
    {
        if ($currentLevel === null) {
            return self::LEVEL_LADDER[0];
        }

        $index = array_search($currentLevel, self::LEVEL_LADDER, true);

        if ($index === false) {
            return null;
        }

        return self::LEVEL_LADDER[$index + 1] ?? $currentLevel;
    }
}
