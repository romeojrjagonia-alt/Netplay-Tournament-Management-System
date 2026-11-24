<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Support\Scheduling\EntrySeeding;
use App\Support\Scheduling\SingleEliminationScheduler;
use Illuminate\Support\Facades\DB;

function seedingCategory(int $teamSize = 1): TournamentCategory
{
    $tournament = Tournament::create([
        'name' => 'Seeding Open',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-10-20',
        'date_end' => '2026-10-22',
        'registration_start' => '2026-10-01',
        'registration_end' => '2026-10-18',
        'status' => 'published',
    ]);

    $category = Category::create(['name' => 'Seeding Category', 'gender' => 'male', 'team_size' => $teamSize]);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);
}

/**
 * Add an approved entry. Each player is [level at application, wins, losses],
 * so a doubles entry takes two. Applications are created in call order, which
 * is their approval order.
 *
 * @param  list<array{0: string, 1: int, 2: int}>  $players
 */
function seedingEntry(TournamentCategory $tournamentCategory, string $name, array $players): PlayerApplication
{
    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);

    foreach ($players as $index => [$level, $wins, $losses]) {
        $player = Player::create(['name' => count($players) > 1 ? "$name ".($index + 1) : $name]);

        if ($wins + $losses > 0) {
            PlayerStatistic::create(['player_id' => $player->id, 'matches_played' => $wins + $losses, 'wins' => $wins, 'losses' => $losses]);
        }

        ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => $level]);
    }

    return $application;
}

/**
 * The approved applications' ids, strongest seed first.
 *
 * @return list<int>
 */
function seededOrder(TournamentCategory $tournamentCategory): array
{
    $entries = $tournamentCategory->playerApplications()
        ->where('status', 'approved')
        ->with('applicationPlayers.player.statistic')
        ->orderBy('id')
        ->get();

    return app(EntrySeeding::class)->rank($entries)->pluck('id')->all();
}

it('seeds a higher level above a lower one, whatever the approval order', function () {
    $category = seedingCategory();
    $beginner = seedingEntry($category, 'Beginner', [['Beginner', 0, 0]]);
    $levelC = seedingEntry($category, 'Level C', [['Level C', 0, 0]]);
    $intermediate = seedingEntry($category, 'Intermediate', [['Intermediate', 0, 0]]);

    expect(seededOrder($category))->toBe([$levelC->id, $intermediate->id, $beginner->id]);
});

it('seeds by win rate within the same level', function () {
    $category = seedingCategory();
    $weak = seedingEntry($category, 'Weak', [['Level D', 2, 8]]);
    $strong = seedingEntry($category, 'Strong', [['Level D', 9, 1]]);
    $middling = seedingEntry($category, 'Middling', [['Level D', 5, 5]]);

    expect(seededOrder($category))->toBe([$strong->id, $middling->id, $weak->id]);
});

it('compares win rate, not the raw number of wins', function () {
    $category = seedingCategory();
    $manyWins = seedingEntry($category, 'Many Wins', [['Level D', 10, 10]]);
    $fewButBetter = seedingEntry($category, 'Few But Better', [['Level D', 4, 1]]);

    expect(seededOrder($category))->toBe([$fewButBetter->id, $manyWins->id]);
});

it('breaks a win rate tie with the longer match history', function () {
    $category = seedingCategory();
    $newcomer = seedingEntry($category, 'Newcomer', [['Level D', 1, 1]]);
    $veteran = seedingEntry($category, 'Veteran', [['Level D', 10, 10]]);

    expect(seededOrder($category))->toBe([$veteran->id, $newcomer->id]);
});

it('ranks level above win rate and win rate above history', function () {
    $category = seedingCategory();
    $lowLevelUndefeated = seedingEntry($category, 'Low Level Undefeated', [['Beginner', 20, 0]]);
    $highLevelNew = seedingEntry($category, 'High Level New', [['Level A', 0, 0]]);
    $sameLevelBetterRate = seedingEntry($category, 'Better Rate', [['Beginner', 4, 1]]);
    $sameLevelLongerHistory = seedingEntry($category, 'Longer History', [['Beginner', 30, 10]]);

    expect(seededOrder($category))->toBe([
        $highLevelNew->id,
        $lowLevelUndefeated->id,
        $sameLevelBetterRate->id,
        $sameLevelLongerHistory->id,
    ]);
});

it('keeps approval order for entries that are level on everything', function () {
    $category = seedingCategory();
    $first = seedingEntry($category, 'First', [['Level B', 3, 3]]);
    $second = seedingEntry($category, 'Second', [['Level B', 3, 3]]);
    $third = seedingEntry($category, 'Third', [['Level B', 3, 3]]);

    expect(seededOrder($category))->toBe([$first->id, $second->id, $third->id]);
});

it('uses the level the player applied at, not their current level', function () {
    $category = seedingCategory();
    $promotedSince = seedingEntry($category, 'Promoted Since', [['Beginner', 0, 0]]);
    $promotedSince->applicationPlayers->first()->player->update(['level' => 'Level A']);
    $appliedHigher = seedingEntry($category, 'Applied Higher', [['Level E', 0, 0]]);

    expect(seededOrder($category))->toBe([$appliedHigher->id, $promotedSince->id]);
});

it('matches levels regardless of capitalisation and ranks unknown levels last', function () {
    $category = seedingCategory();
    $unknown = seedingEntry($category, 'Unknown', [['whatever', 0, 0]]);
    $typed = seedingEntry($category, 'Typed', [['  level b ', 0, 0]]);
    $beginner = seedingEntry($category, 'Beginner', [['BEGINNER', 0, 0]]);

    expect(seededOrder($category))->toBe([$typed->id, $beginner->id, $unknown->id]);
});

it('treats a doubles entry as the average of its two players', function () {
    $category = seedingCategory(teamSize: 2);
    $mismatched = seedingEntry($category, 'Mismatched', [['Level A', 0, 0], ['Beginner', 0, 0]]);
    $evenlyMatched = seedingEntry($category, 'Evenly Matched', [['Level D', 0, 0], ['Level D', 0, 0]]);

    // Level A and Beginner average out to the Level E rung, below Level D.
    expect(seededOrder($category))->toBe([$evenlyMatched->id, $mismatched->id]);
});

it('places the strongest seeds in the bracket slots seeding gives them', function () {
    $category = seedingCategory();
    // Approved weakest first, so approval order alone would seed them backwards.
    $entries = [
        seedingEntry($category, 'Seed 4', [['Beginner', 0, 0]]),
        seedingEntry($category, 'Seed 3', [['Intermediate', 0, 0]]),
        seedingEntry($category, 'Seed 2', [['Level D', 5, 5]]),
        seedingEntry($category, 'Seed 1', [['Level D', 9, 1]]),
    ];

    $slots = app(SingleEliminationScheduler::class)->arrangement($category);

    // Seed order for a 4-draw is [1, 4, 2, 3].
    expect($slots)->toBe([$entries[3]->id, $entries[0]->id, $entries[2]->id, $entries[1]->id]);
});

it('draws the top seed against the weakest and the top two seeds into opposite halves', function () {
    $category = seedingCategory();
    $levels = ['Beginner', 'Intermediate', 'Level G', 'Level F', 'Level E', 'Level D', 'Level C', 'Level B'];

    // Approved weakest to strongest, so approval order is the reverse of strength.
    $entries = collect($levels)->map(fn (string $level) => seedingEntry($category, $level, [[$level, 0, 0]]));
    $strongest = $entries[7];
    $secondStrongest = $entries[6];
    $weakest = $entries[0];

    app(SingleEliminationScheduler::class)->generate($category);

    $round1 = Matches::where('tournament_category_id', $category->id)->where('round', 1)->orderBy('position')->get();
    $idsIn = fn ($matches) => $matches->flatMap(fn (Matches $match) => [$match->applicationForSide('A')?->id, $match->applicationForSide('B')?->id]);

    expect([$round1[0]->applicationForSide('A')->id, $round1[0]->applicationForSide('B')->id])
        ->toBe([$strongest->id, $weakest->id])
        ->and($idsIn($round1->take(2))->contains($strongest->id))->toBeTrue()
        ->and($idsIn($round1->slice(2))->contains($secondStrongest->id))->toBeTrue();
});

it("lets an admin's placement override the statistic seeding", function () {
    $category = seedingCategory();
    $weak = seedingEntry($category, 'Weak', [['Beginner', 0, 0]]);
    $strong = seedingEntry($category, 'Strong', [['Level A', 9, 1]]);
    $middle = seedingEntry($category, 'Middle', [['Level D', 0, 0]]);
    $other = seedingEntry($category, 'Other', [['Level C', 0, 0]]);

    // The admin pins the weakest entry into the top seed's slot.
    $weak->update(['draw_position' => 1]);

    $slots = app(SingleEliminationScheduler::class)->arrangement($category);

    // The rest are still seeded by strength into the free slots: seed 2 at slot 3, seed 3 at slot 4, seed 4 at slot 2.
    expect($slots)->toBe([$weak->id, $middle->id, $strong->id, $other->id]);
});

it('loads the statistics in a fixed number of queries', function () {
    $category = seedingCategory();

    foreach (range(1, 8) as $number) {
        seedingEntry($category, "Player $number", [['Level D', $number, 1]]);
    }

    DB::enableQueryLog();
    app(SingleEliminationScheduler::class)->arrangement($category);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThan(10);
});
