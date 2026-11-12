<?php

namespace App\Support\Scheduling;

use App\Models\Category;
use App\Models\PlayerApplication;
use Illuminate\Support\Collection;

/**
 * Ranks a category's entries for seeding: strongest first. Entries are
 * compared on the level the players applied at (NETPLAY.md 4.5), then on win
 * rate, then on how many matches they have played, and anything still tied
 * stays in approval order. A doubles entry counts as the average of its
 * players.
 */
class EntrySeeding
{
    /**
     * @param  Collection<int, PlayerApplication>  $entries  in approval order, with `applicationPlayers.player.statistic` loaded
     * @return Collection<int, PlayerApplication> strongest seed first
     */
    public function rank(Collection $entries): Collection
    {
        return $entries
            ->values()
            ->map(fn (PlayerApplication $entry, int $approvalIndex): array => [
                'entry' => $entry,
                'approvalIndex' => $approvalIndex,
                ...$this->strength($entry),
            ])
            ->sortBy([
                ['level', 'desc'],
                ['winRate', 'desc'],
                ['matchesPlayed', 'desc'],
                ['approvalIndex', 'asc'],
            ])
            ->pluck('entry')
            ->values();
    }

    /**
     * @return array{level: float, winRate: float, matchesPlayed: float}
     */
    public function strength(PlayerApplication $entry): array
    {
        $players = $entry->applicationPlayers;

        if ($players->isEmpty()) {
            return ['level' => -1.0, 'winRate' => 0.0, 'matchesPlayed' => 0.0];
        }

        return [
            'level' => (float) $players->avg(fn ($applicationPlayer): int => $this->levelRank($applicationPlayer->level_at_apply)),
            'winRate' => (float) $players->avg(function ($applicationPlayer): float {
                $statistic = $applicationPlayer->player?->statistic;

                return $statistic && $statistic->matches_played > 0
                    ? $statistic->wins / $statistic->matches_played
                    : 0.0;
            }),
            'matchesPlayed' => (float) $players->avg(fn ($applicationPlayer): int => $applicationPlayer->player?->statistic?->matches_played ?? 0),
        ];
    }

    /**
     * Where a level sits on the ladder, higher meaning stronger. Levels the
     * ladder doesn't know (players type their own) rank below Beginner.
     */
    public function levelRank(?string $level): int
    {
        $ladder = array_map('strtolower', Category::LEVELS);
        $position = array_search(strtolower(trim((string) $level)), $ladder, true);

        return $position === false ? -1 : $position;
    }
}
