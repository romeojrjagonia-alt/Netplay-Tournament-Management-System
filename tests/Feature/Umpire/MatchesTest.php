<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function umpireMatchesTournamentCategory(array $categoryOverrides = []): TournamentCategory
{
    $tournament = Tournament::create([
        'name' => 'Test Tournament',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-07-01',
        'date_end' => '2026-07-31',
        'time' => '9:00 AM - 5:00 PM',
        'specific_days' => 'SAT, SUN',
        'registration_start' => '2026-06-01',
        'registration_end' => '2026-12-31',
        'status' => 'published',
    ]);

    $category = Category::create(array_merge([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
    ], $categoryOverrides));

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);
}

function umpireMatchesApplicationFor(TournamentCategory $tournamentCategory, Player $player): PlayerApplication
{
    $application = PlayerApplication::create([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => 'approved',
    ]);

    ApplicationPlayer::create([
        'application_id' => $application->id,
        'player_id' => $player->id,
        'level_at_apply' => 'Beginner',
    ]);

    return $application;
}

function umpireMatchesMatch(TournamentCategory $tournamentCategory, array $overrides = []): Matches
{
    return Matches::create(array_merge([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 1',
    ], $overrides));
}

test('a non-umpire cannot view the assigned matches page', function () {
    $user = User::factory()->create(['role' => 'player']);

    $this->actingAs($user);

    Volt::test('umpire.matches')->assertForbidden();
});

test('the assigned matches page only shows matches assigned to the current umpire', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $otherUmpire = User::factory()->create(['role' => 'umpire']);

    $tournamentCategory = umpireMatchesTournamentCategory();
    $applicationA = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => 'Mark Santos']));
    $applicationB = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => 'Juan Torres']));
    $strangerApplication = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => 'Stranger Player']));

    $myMatch = umpireMatchesMatch($tournamentCategory, ['assigned_umpire_id' => $umpire->id, 'court' => 'Court 2']);
    MatchApplication::create(['match_id' => $myMatch->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $myMatch->id, 'application_id' => $applicationB->id]);

    $othersMatch = umpireMatchesMatch($tournamentCategory, ['assigned_umpire_id' => $otherUmpire->id, 'court' => 'Court 9']);
    MatchApplication::create(['match_id' => $othersMatch->id, 'application_id' => $strangerApplication->id]);

    $this->actingAs($umpire);

    Volt::test('umpire.matches')
        ->assertSee('Mark Santos')
        ->assertSee('Juan Torres')
        ->assertDontSee('Stranger Player');
});

test('filter tabs only show assigned matches in the relevant status bucket', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $tournamentCategory = umpireMatchesTournamentCategory();
    $applicationA = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => 'Mark Santos']));
    $applicationB = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => 'Juan Torres']));

    $upcomingMatch = umpireMatchesMatch($tournamentCategory, [
        'assigned_umpire_id' => $umpire->id,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 1',
    ]);
    MatchApplication::create(['match_id' => $upcomingMatch->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $upcomingMatch->id, 'application_id' => $applicationB->id]);

    $completedMatch = umpireMatchesMatch($tournamentCategory, [
        'assigned_umpire_id' => $umpire->id,
        'round' => 2,
        'winner_application_id' => $applicationA->id,
        'status' => 'completed',
        'scheduled_at' => now()->subDay(),
        'court' => 'Court 2',
    ]);
    MatchApplication::create(['match_id' => $completedMatch->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $completedMatch->id, 'application_id' => $applicationB->id]);

    $this->actingAs($umpire);

    Volt::test('umpire.matches')
        ->set('tab', 'upcoming')
        ->assertSee('Court 1')
        ->assertDontSee('Court 2');

    Volt::test('umpire.matches')
        ->set('tab', 'concluded')
        ->assertSee('Court 2')
        ->assertDontSee('Court 1');
});

test('a completed assigned match shows the winning team, not a win/lose badge', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $tournamentCategory = umpireMatchesTournamentCategory();
    $applicationA = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => 'Mark Santos']));
    $applicationB = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => 'Juan Torres']));

    $match = umpireMatchesMatch($tournamentCategory, [
        'assigned_umpire_id' => $umpire->id,
        'winner_application_id' => $applicationB->id,
        'status' => 'completed',
        'scheduled_at' => now()->subDay(),
    ]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationB->id]);

    $this->actingAs($umpire);

    Volt::test('umpire.matches')
        ->assertSee('Winner')
        ->assertSee('Team B')
        ->assertDontSee('WIN')
        ->assertDontSee('LOSE');
});

function umpireMatchesPairedMatch(User $umpire, array $overrides = [], string $nameA = 'Mark Santos', string $nameB = 'Juan Torres'): Matches
{
    $tournamentCategory = umpireMatchesTournamentCategory();
    $applicationA = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => $nameA]));
    $applicationB = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => $nameB]));

    $match = umpireMatchesMatch($tournamentCategory, array_merge(['assigned_umpire_id' => $umpire->id], $overrides));
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationA->id, 'slot' => 1]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationB->id, 'slot' => 2]);

    return $match;
}

test('an upcoming assigned match links to its scoreboard', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = umpireMatchesPairedMatch($umpire);

    $this->actingAs($umpire);

    Volt::test('umpire.matches')
        ->assertSee(route('umpire.matches.scoring', $match), false)
        ->assertSee('Tap to open the scoreboard and start scoring.');
});

test('a match still waiting for a team cannot be opened for scoring', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $tournamentCategory = umpireMatchesTournamentCategory();
    $application = umpireMatchesApplicationFor($tournamentCategory, Player::create(['name' => 'Lonely Player']));
    $match = umpireMatchesMatch($tournamentCategory, ['assigned_umpire_id' => $umpire->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $application->id]);

    $this->actingAs($umpire);

    Volt::test('umpire.matches')
        ->assertDontSee(route('umpire.matches.scoring', $match), false)
        ->assertSee('Waiting for both teams');
});

test('assigned matches can be searched by player or court', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    umpireMatchesPairedMatch($umpire, ['court' => 'Court 7'], 'Mark Santos', 'Juan Torres');
    umpireMatchesPairedMatch($umpire, ['court' => 'Court 8'], 'Pedro Reyes', 'Ana Cruz');

    $this->actingAs($umpire);

    Volt::test('umpire.matches')
        ->set('search', 'Pedro')
        ->assertSee('Pedro Reyes')
        ->assertDontSee('Mark Santos')
        ->set('search', 'Court 7')
        ->assertSee('Mark Santos')
        ->assertDontSee('Pedro Reyes');
});

test('a concluded match summary shows the winner, scores and scoresheet links', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = umpireMatchesPairedMatch($umpire, ['status' => 'completed', 'scheduled_at' => now()->subDay()]);
    $match->update(['winner_application_id' => $match->matchApplications->first()->application_id]);

    $this->actingAs($umpire);

    Volt::test('umpire.match-summary')
        ->dispatch('open-match-summary', matchId: $match->id)
        ->assertSee('Match Summary')
        ->assertSee('Mark Santos')
        ->assertSee('WINNER')
        ->assertSee(route('umpire.matches.scoresheet', $match), false)
        ->assertSee('Print Scoresheet');
});

test('a match summary is not shown for another umpire\'s or an unfinished match', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $other = User::factory()->create(['role' => 'umpire']);
    $othersMatch = umpireMatchesPairedMatch($other, ['status' => 'completed'], 'Hidden One', 'Hidden Two');
    $openMatch = umpireMatchesPairedMatch($umpire, ['status' => 'scheduled'], 'Open One', 'Open Two');

    $this->actingAs($umpire);

    Volt::test('umpire.match-summary')
        ->dispatch('open-match-summary', matchId: $othersMatch->id)
        ->assertDontSee('Hidden One')
        ->dispatch('open-match-summary', matchId: $openMatch->id)
        ->assertDontSee('Open One');
});

test('concluded matches on the assigned matches page open the summary', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    umpireMatchesPairedMatch($umpire, ['status' => 'completed', 'scheduled_at' => now()->subDay()]);

    $this->actingAs($umpire);

    Volt::test('umpire.matches')
        ->set('tab', 'concluded')
        ->assertSeeHtml('open-match-summary');
});
