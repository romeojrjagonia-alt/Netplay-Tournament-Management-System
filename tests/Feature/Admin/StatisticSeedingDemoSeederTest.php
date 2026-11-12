<?php

use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Support\Scheduling\SingleEliminationScheduler;
use Database\Seeders\StatisticSeedingDemoSeeder;

function demoNames(TournamentCategory $tournamentCategory): array
{
    $slots = app(SingleEliminationScheduler::class)->arrangement($tournamentCategory);

    return collect($slots)->map(fn (?int $id) => $id
        ? $tournamentCategory->playerApplications()->with('applicationPlayers.player')->find($id)->applicationPlayers->first()->player->name
        : null)->all();
}

it('seeds a demo tournament whose default bracket follows statistics', function () {
    $this->seed(StatisticSeedingDemoSeeder::class);

    $tournament = Tournament::where('name', StatisticSeedingDemoSeeder::TOURNAMENT)->firstOrFail();
    $singles = $tournament->tournamentCategories()->whereHas('category', fn ($q) => $q->where('team_size', 1))->firstOrFail();

    expect($tournament->tournamentCategories)->toHaveCount(2)
        ->and($singles->hasGeneratedDraw())->toBeFalse()
        // Seed order for 8 is [1, 8, 4, 5, 2, 7, 3, 6].
        ->and(demoNames($singles))->toBe([
            'Alex Ace',
            'Wally Newbie',
            'Dominic Dominant',
            'Dante Steady',
            'Bryan Ranked',
            'Ian Intermediate',
            'Cesar Climber',
            'Gabe Grinder',
        ]);
});

it('does nothing when run a second time', function () {
    $this->seed(StatisticSeedingDemoSeeder::class);
    $this->seed(StatisticSeedingDemoSeeder::class);

    expect(Tournament::where('name', StatisticSeedingDemoSeeder::TOURNAMENT)->count())->toBe(1);
});
