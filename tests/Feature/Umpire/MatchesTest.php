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
