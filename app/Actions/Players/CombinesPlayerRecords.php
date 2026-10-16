<?php

namespace App\Actions\Players;

use App\Models\ApplicationPlayer;
use App\Models\Player;
use App\Models\PlayerStatistic;
use InvalidArgumentException;

/**
 * Steps shared by the actions that fold one player record into another.
 */
trait CombinesPlayerRecords
{
    /**
     * A player can only be in one live entry per draw, so refuse a merge that
     * would put the same person in one category twice.
     *
     * @throws InvalidArgumentException
     */
    private function assertNoSharedCategory(Player $first, Player $second): void
    {
        $categoryIds = fn (Player $player) => ApplicationPlayer::query()
            ->where('player_id', $player->id)
            ->whereHas('application', fn ($query) => $query->where('status', '!=', 'rejected'))
            ->join('player_applications', 'player_applications.id', '=', 'application_players.application_id')
            ->pluck('player_applications.tournament_category_id');

        if ($categoryIds($first)->intersect($categoryIds($second))->isNotEmpty()) {
            throw new InvalidArgumentException('Both records have an active entry in the same category, so they can\'t be merged automatically.');
        }
    }

    /**
     * Add the statistics of `$from` onto `$into`.
     */
    private function combineStatistics(Player $into, Player $from): void
    {
        $fromStats = PlayerStatistic::find($from->id);

        if (! $fromStats) {
            return;
        }

        $stats = PlayerStatistic::firstOrCreate(
            ['player_id' => $into->id],
            ['matches_played' => 0, 'wins' => 0, 'losses' => 0],
        );

        $stats->update([
            'matches_played' => $stats->matches_played + $fromStats->matches_played,
            'wins' => $stats->wins + $fromStats->wins,
            'losses' => $stats->losses + $fromStats->losses,
        ]);
    }
}
