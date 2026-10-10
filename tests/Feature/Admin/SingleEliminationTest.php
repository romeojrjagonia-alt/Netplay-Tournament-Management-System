<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Support\Scheduling\SingleEliminationScheduler;

/**
 * @return array{0: TournamentCategory, 1: list<PlayerApplication>}
 */
function eliminationCategory(int $entries): array
{
    $tournament = Tournament::create([
        'name' => 'Knockout Open',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-10-20',
        'date_end' => '2026-10-22',
        'registration_start' => '2026-10-01',
        'registration_end' => '2026-10-18',
        'status' => 'published',
    ]);

    $category = Category::create(['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1]);

    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);

    $applications = [];

    for ($i = 1; $i <= $entries; $i++) {
        $player = Player::create(['name' => "Player $i"]);
        $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
        ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);
        $applications[] = $application;
    }

    return [$tournamentCategory, $applications];
}

it('seeds a bracket so the top seeds meet last', function () {
    expect(app(SingleEliminationScheduler::class)->seedOrder(8))->toBe([1, 8, 4, 5, 2, 7, 3, 6]);
});

it('generates every round up front with byes completed and advanced', function () {
    [$tournamentCategory, $applications] = eliminationCategory(5);

    $created = app(SingleEliminationScheduler::class)->generate($tournamentCategory);

    expect($created)->toBe(7);

    $round1 = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 1)->orderBy('position')->get();

    // Seeds 1, 2 and 3 get byes against the three empty seeds.
    expect($round1->where('status', 'completed'))->toHaveCount(3);

    $round2 = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 2)->orderBy('position')->get();

    // Position 1 gets the seed 1 bye (slot 1) and awaits the seed 4 v 5 winner (slot 2).
    expect($round2[0]->applicationForSide('A')?->id)->toBe($applications[0]->id)
        ->and($round2[0]->applicationForSide('B'))->toBeNull()
        // Position 2 gets seed 2 (slot 1) and seed 3 (slot 2).
        ->and($round2[1]->applicationForSide('A')?->id)->toBe($applications[1]->id)
        ->and($round2[1]->applicationForSide('B')?->id)->toBe($applications[2]->id);
});

it('does not count a bye as a played match', function () {
    [$tournamentCategory, $applications] = eliminationCategory(3);

    app(SingleEliminationScheduler::class)->generate($tournamentCategory);

    expect(PlayerStatistic::count())->toBe(0);
});

it('advances a winner into the next round slot by position', function () {
    [$tournamentCategory, $applications] = eliminationCategory(4);

    app(SingleEliminationScheduler::class)->generate($tournamentCategory);

    $round1 = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 1)->orderBy('position')->get();

    // Complete position 2 first: its winner must still land in slot 2 of the final.
    $round1[1]->update(['status' => 'completed', 'winner_application_id' => $round1[1]->applicationForSide('B')->id]);

    $final = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 2)->first();

    expect($final->applicationForSide('A'))->toBeNull()
        ->and($final->applicationForSide('B')?->id)->toBe($round1[1]->applicationForSide('B')->id);

    $round1[0]->update(['status' => 'completed', 'winner_application_id' => $round1[0]->applicationForSide('A')->id]);

    $final = $final->fresh();

    expect($final->applicationForSide('A')?->id)->toBe($round1[0]->applicationForSide('A')->id)
        ->and($final->matchApplications)->toHaveCount(2);
});

it('refuses to generate twice or with fewer than two entries', function () {
    [$tournamentCategory] = eliminationCategory(1);

    expect(fn () => app(SingleEliminationScheduler::class)->generate($tournamentCategory))
        ->toThrow(InvalidArgumentException::class);

    [$tournamentCategory] = eliminationCategory(2);
    app(SingleEliminationScheduler::class)->generate($tournamentCategory);

    expect(fn () => app(SingleEliminationScheduler::class)->generate($tournamentCategory))
        ->toThrow(InvalidArgumentException::class);
});
