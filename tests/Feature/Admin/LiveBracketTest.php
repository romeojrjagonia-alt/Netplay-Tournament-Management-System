<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Scheduling\SingleEliminationScheduler;
use Livewire\Volt\Volt;

function liveBracketCategory(int $entries = 4, bool $generate = true): TournamentCategory
{
    $tournament = Tournament::create([
        'name' => 'Live Bracket Open',
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

    foreach (range(1, $entries) as $number) {
        $player = Player::create(['name' => "Live Player $number"]);
        $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
        ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);
    }

    if ($generate) {
        app(SingleEliminationScheduler::class)->generate($tournamentCategory);
    }

    return $tournamentCategory;
}

function liveBracketPage(TournamentCategory $tournamentCategory)
{
    return Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.events.schedule.show', ['tournament' => $tournamentCategory->tournament, 'tournamentCategory' => $tournamentCategory]);
}

it('polls for results once the bracket is generated', function () {
    liveBracketPage(liveBracketCategory())->assertSeeHtml('wire:poll.10s.visible="refreshWhenResultsChange"');
});

it('does not poll while the bracket is still being arranged', function () {
    liveBracketPage(liveBracketCategory(generate: false))->assertDontSeeHtml('refreshWhenResultsChange');
});

it('leaves the page alone while no result has come in', function () {
    $component = liveBracketPage(liveBracketCategory());
    $version = $component->get('matchesVersion');

    $component->call('refreshWhenResultsChange')->assertSet('matchesVersion', $version);
});

it("redraws the bracket with a match's result once the umpire finishes scoring", function () {
    $tournamentCategory = liveBracketCategory();
    $component = liveBracketPage($tournamentCategory);
    $before = $component->get('matchesVersion');

    expect(collect($component->instance()->viewerData['matches'])->where('status', 4))->toHaveCount(0);

    $this->travel(1)->minutes();

    $match = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 1)->orderBy('position')->firstOrFail();
    $match->update(['status' => 'completed', 'winner_application_id' => $match->applicationForSide('A')->id]);

    $component->call('refreshWhenResultsChange');

    expect($component->get('matchesVersion'))->not->toBe($before)
        ->and(collect($component->instance()->viewerData['matches'])->where('status', 4))->toHaveCount(1);
});

it('notices a result that lands in the same minute as the last one', function () {
    $tournamentCategory = liveBracketCategory();
    $component = liveBracketPage($tournamentCategory);
    $matches = Matches::where('tournament_category_id', $tournamentCategory->id)->where('round', 1)->orderBy('position')->get();

    $matches[0]->update(['status' => 'completed', 'winner_application_id' => $matches[0]->applicationForSide('A')->id]);
    $component->call('refreshWhenResultsChange');
    $afterFirst = $component->get('matchesVersion');

    $matches[1]->update(['status' => 'completed', 'winner_application_id' => $matches[1]->applicationForSide('A')->id]);
    $component->call('refreshWhenResultsChange');

    expect($component->get('matchesVersion'))->not->toBe($afterFirst);
});
