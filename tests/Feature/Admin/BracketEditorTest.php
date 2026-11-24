<?php

use App\Actions\Applications\RerouteApplication;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Bracket\BracketsViewerData;
use App\Support\Scheduling\SingleEliminationScheduler;
use Livewire\Volt\Volt;

/**
 * @return array{0: TournamentCategory, 1: list<PlayerApplication>}
 */
function arrangeableCategory(int $entries, string $format = 'single_elimination'): array
{
    $tournament = Tournament::create([
        'name' => 'Arrange Open',
        'format' => $format,
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

    foreach (range(1, $entries) as $number) {
        $player = Player::create(['name' => "Player $number"]);
        $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
        ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);
        $applications[] = $application;
    }

    return [$tournamentCategory, $applications];
}

function arrangementPage(TournamentCategory $tournamentCategory)
{
    return Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.events.schedule.show', [
            'tournament' => $tournamentCategory->tournament,
            'tournamentCategory' => $tournamentCategory,
        ]);
}

it('defaults the line-up to the seeded bracket', function () {
    [$tournamentCategory, $applications] = arrangeableCategory(4);

    $ids = array_map(fn ($application) => $application->id, $applications);

    // Seed order for 4 is [1, 4, 2, 3].
    expect(app(SingleEliminationScheduler::class)->arrangement($tournamentCategory))
        ->toBe([$ids[0], $ids[3], $ids[1], $ids[2]]);
});

it('swaps two slots and generates the bracket in that order', function () {
    [$tournamentCategory, $applications] = arrangeableCategory(4);
    $scheduler = app(SingleEliminationScheduler::class);

    [$a, $b, $c, $d] = array_map(fn ($application) => $application->id, $applications);

    // Default: match 1 = A v D, match 2 = B v C. Swap A (slot 0) with C (slot 3).
    $scheduler->swap($tournamentCategory, 0, 3);

    expect($scheduler->arrangement($tournamentCategory))->toBe([$c, $d, $b, $a]);

    $scheduler->generate($tournamentCategory);

    $round1 = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 1)->orderBy('position')->get();

    expect($round1[0]->applicationForSide('A')->id)->toBe($c)
        ->and($round1[0]->applicationForSide('B')->id)->toBe($d)
        ->and($round1[1]->applicationForSide('A')->id)->toBe($b)
        ->and($round1[1]->applicationForSide('B')->id)->toBe($a);
});

it('lets an entry be moved into and out of a bye slot', function () {
    [$tournamentCategory, $applications] = arrangeableCategory(3);
    $scheduler = app(SingleEliminationScheduler::class);

    [$a, $b, $c] = array_map(fn ($application) => $application->id, $applications);

    // Size 4, seed order [1, 4, 2, 3]: A v BYE, B v C.
    expect($scheduler->arrangement($tournamentCategory))->toBe([$a, null, $b, $c]);

    // Give C the bye instead: swap slot 3 (C) with slot 1 (BYE).
    $scheduler->swap($tournamentCategory, 3, 1);

    expect($scheduler->arrangement($tournamentCategory))->toBe([$a, $c, $b, null]);

    $scheduler->generate($tournamentCategory);

    $round1 = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 1)->orderBy('position')->get();

    // A v C is a real match now; B has the bye and advances.
    expect($round1[0]->status)->toBe('scheduled')
        ->and($round1[1]->status)->toBe('completed')
        ->and($round1[1]->winner_application_id)->toBe($b);

    $final = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 2)->first();

    expect($final->applicationForSide('B')->id)->toBe($b);
});

it('keeps the arrangement when a new entry is approved afterwards', function () {
    [$tournamentCategory, $applications] = arrangeableCategory(4);
    $scheduler = app(SingleEliminationScheduler::class);

    [$a, $b, $c, $d] = array_map(fn ($application) => $application->id, $applications);

    $scheduler->swap($tournamentCategory, 0, 3);

    $late = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);

    $slots = $scheduler->arrangement($tournamentCategory);

    expect($slots)->toHaveCount(8)
        ->and($slots[0])->toBe($c)
        ->and($slots[1])->toBe($d)
        ->and($slots[2])->toBe($b)
        ->and($slots[3])->toBe($a)
        ->and($slots)->toContain($late->id);
});

it('resets to the seeded order', function () {
    [$tournamentCategory] = arrangeableCategory(4);
    $scheduler = app(SingleEliminationScheduler::class);

    $default = $scheduler->arrangement($tournamentCategory);

    $scheduler->swap($tournamentCategory, 0, 3);
    $scheduler->resetArrangement($tournamentCategory);

    expect($scheduler->arrangement($tournamentCategory))->toBe($default)
        ->and(PlayerApplication::whereNotNull('draw_position')->count())->toBe(0);
});

it('refuses to swap an out-of-range slot or a generated bracket', function () {
    [$tournamentCategory] = arrangeableCategory(4);
    $scheduler = app(SingleEliminationScheduler::class);

    expect(fn () => $scheduler->swap($tournamentCategory, 0, 9))->toThrow(InvalidArgumentException::class);

    $scheduler->generate($tournamentCategory);

    expect(fn () => $scheduler->swap($tournamentCategory, 0, 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $scheduler->resetArrangement($tournamentCategory))->toThrow(InvalidArgumentException::class);
});

it('swaps slots from the schedule page and shows the pairings', function () {
    [$tournamentCategory] = arrangeableCategory(4);

    arrangementPage($tournamentCategory)
        ->assertSee('Round 1')
        ->assertSee('Match 1')
        ->call('swapSlots', 0, 3);

    $slots = app(SingleEliminationScheduler::class)->arrangement($tournamentCategory);

    expect(PlayerApplication::find($slots[0])->draw_position)->toBe(1)
        ->and(PlayerApplication::find($slots[3])->draw_position)->toBe(4);
});

it('does not offer the bracket editor for a round robin', function () {
    [$tournamentCategory] = arrangeableCategory(4, 'round_robin');

    arrangementPage($tournamentCategory)->assertDontSee('Reset to seeded order');
});

it('clears the draw position when an entry is rerouted', function () {
    [$tournamentCategory, $applications] = arrangeableCategory(4);

    app(SingleEliminationScheduler::class)->swap($tournamentCategory, 0, 1);

    $otherCategory = Category::create(['name' => "Men's Singles B", 'gender' => 'male', 'team_size' => 1]);

    $other = TournamentCategory::create([
        'tournament_id' => $tournamentCategory->tournament_id,
        'category_id' => $otherCategory->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);

    $application = $applications[0]->fresh();

    expect($application->draw_position)->not->toBeNull();

    app(RerouteApplication::class)->handle($application, $other, true);

    expect($application->fresh()->draw_position)->toBeNull();
});

it('builds brackets-viewer data with byes, tbd slots, scores and winners', function () {
    [$tournamentCategory, $applications] = arrangeableCategory(3);

    app(SingleEliminationScheduler::class)->generate($tournamentCategory);

    $data = app(BracketsViewerData::class)->for($tournamentCategory);

    expect($data['stages'][0]['type'])->toBe('single_elimination')
        ->and($data['stages'][0]['settings']['size'])->toBe(4)
        ->and($data['participants'])->toHaveCount(3)
        ->and($data['matches'])->toHaveCount(3);

    [$byeMatch, , $final] = $data['matches'];

    // Round 1 match 1: the first seed's bye.
    expect($byeMatch['status'])->toBe(4)
        ->and($byeMatch['opponent2'])->toBeNull()
        ->and($byeMatch['opponent1']['result'])->toBe('win');

    // The final already holds the bye's winner and waits on the other match.
    expect($final['opponent1']['id'])->toBe($applications[0]->id)
        ->and($final['opponent2'])->toBe(['id' => null])
        ->and($final['status'])->toBe(1);

    $match = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 1)->where('position', 2)->first();
    $winner = $match->applicationForSide('A');

    foreach ([1, 2] as $number) {
        MatchGame::create(['match_id' => $match->id, 'game_number' => $number, 'winner_application_id' => $winner->id]);
    }

    $match->update(['status' => 'completed', 'winner_application_id' => $winner->id]);

    $played = app(BracketsViewerData::class)->for($tournamentCategory)['matches'][1];

    expect($played['status'])->toBe(4)
        ->and($played['opponent1'])->toBe(['id' => $winner->id, 'score' => 2, 'result' => 'win'])
        ->and($played['opponent2']['result'])->toBe('loss')
        ->and($played['opponent2']['score'])->toBe(0);
});

it('renders a generated bracket that contains byes and a finished match', function () {
    [$tournamentCategory] = arrangeableCategory(3);

    app(SingleEliminationScheduler::class)->generate($tournamentCategory);

    $match = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 1)->where('position', 2)->first();
    $match->update(['status' => 'completed', 'winner_application_id' => $match->applicationForSide('A')->id]);

    expect($match->fresh()->winnerSide())->toBe('A');

    arrangementPage($tournamentCategory)
        ->assertSuccessful()
        ->assertSee('Round 2')
        ->assertDontSee('Reset to seeded order');
});
