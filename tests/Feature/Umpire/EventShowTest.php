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

function eventShowTournamentCategory(Tournament $tournament): TournamentCategory
{
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

function eventShowTournament(array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
        'name' => 'Inato Tournament',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->subDay()->toDateString(),
        'date_end' => now()->addMonth()->toDateString(),
        'time' => '9:00 AM - 5:00 PM',
        'specific_days' => 'SAT, SUN',
        'registration_start' => '2026-06-01',
        'registration_end' => '2026-12-31',
        'status' => 'published',
    ], $overrides));
}

function eventShowApplicationFor(TournamentCategory $tournamentCategory, Player $player): PlayerApplication
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

test('a non-umpire cannot view an event page', function () {
    $tournament = eventShowTournament();
    $user = User::factory()->create(['role' => 'player']);

    $this->actingAs($user);

    Volt::test('umpire.events.show', ['tournament' => $tournament])->assertForbidden();
});

test('an umpire with no matches assigned in this tournament cannot view it', function () {
    $tournament = eventShowTournament();
    $umpire = User::factory()->create(['role' => 'umpire']);

    $this->actingAs($umpire);

    Volt::test('umpire.events.show', ['tournament' => $tournament])->assertForbidden();
});

test('the event page only lists this umpire\'s matches within the tournament', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $otherUmpire = User::factory()->create(['role' => 'umpire']);

    $tournament = eventShowTournament();
    $tournamentCategory = eventShowTournamentCategory($tournament);
    $applicationA = eventShowApplicationFor($tournamentCategory, Player::create(['name' => 'Mark Santos']));
    $applicationB = eventShowApplicationFor($tournamentCategory, Player::create(['name' => 'Juan Torres']));

    $myMatch = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 2',
        'assigned_umpire_id' => $umpire->id,
    ]);
    MatchApplication::create(['match_id' => $myMatch->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $myMatch->id, 'application_id' => $applicationB->id]);

    $othersMatch = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 2,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 9',
        'assigned_umpire_id' => $otherUmpire->id,
    ]);
    MatchApplication::create(['match_id' => $othersMatch->id, 'application_id' => $applicationA->id]);

    $this->actingAs($umpire);

    Volt::test('umpire.events.show', ['tournament' => $tournament])
        ->assertSee('Court 2')
        ->assertDontSee('Court 9');
});
