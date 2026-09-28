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

function umpireDashboardTournamentCategory(array $tournamentOverrides = []): TournamentCategory
{
    $tournament = Tournament::create(array_merge([
        'name' => 'Inato Tournament',
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
    ], $tournamentOverrides));

    $category = Category::create([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
    ]);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);
}

function umpireDashboardApplicationFor(TournamentCategory $tournamentCategory, Player $player): PlayerApplication
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

function umpireDashboardMatch(TournamentCategory $tournamentCategory, array $overrides = []): Matches
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

test('a non-umpire cannot view the dashboard', function () {
    $user = User::factory()->create(['role' => 'player']);

    $this->actingAs($user);

    Volt::test('umpire.dashboard')->assertForbidden();
});

test('the dashboard only counts and lists matches assigned to the current umpire', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $otherUmpire = User::factory()->create(['role' => 'umpire']);

    $tournamentCategory = umpireDashboardTournamentCategory();
    $applicationA = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Mark Santos']));
    $applicationB = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Juan Torres']));

    $myMatch = umpireDashboardMatch($tournamentCategory, [
        'assigned_umpire_id' => $umpire->id,
        'court' => 'Court 2',
        'scheduled_at' => now()->addDay(),
    ]);
    MatchApplication::create(['match_id' => $myMatch->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $myMatch->id, 'application_id' => $applicationB->id]);

    $otherMatch = umpireDashboardMatch($tournamentCategory, [
        'assigned_umpire_id' => $otherUmpire->id,
        'court' => 'Court 9',
    ]);
    MatchApplication::create(['match_id' => $otherMatch->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $otherMatch->id, 'application_id' => $applicationB->id]);

    $this->actingAs($umpire);

    Volt::test('umpire.dashboard')
        ->assertSee('Inato Tournament')
        ->assertSee('Court 2')
        ->assertDontSee('Court 9')
        ->assertSet('assignedMatchesCount', 1)
        ->assertSet('accomplishedMatchesCount', 0);
});

test('the previous match result shows the winning team for a completed assigned match', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $tournamentCategory = umpireDashboardTournamentCategory();
    $applicationA = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Mark Santos']));
    $applicationB = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Juan Torres']));

    $match = umpireDashboardMatch($tournamentCategory, [
        'assigned_umpire_id' => $umpire->id,
        'status' => 'completed',
        'winner_application_id' => $applicationA->id,
        'scheduled_at' => now()->subDay(),
    ]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationB->id]);

    $this->actingAs($umpire);

    Volt::test('umpire.dashboard')
        ->assertSee('Winner')
        ->assertSee('Team A')
        ->assertSet('accomplishedMatchesCount', 1);
});
