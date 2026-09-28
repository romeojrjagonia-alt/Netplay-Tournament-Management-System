<?php

use App\Models\Category;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function umpireEventsTournamentCategory(Tournament $tournament): TournamentCategory
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

function umpireEventsTournament(array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
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
    ], $overrides));
}

test('a non-umpire cannot view the events page', function () {
    $user = User::factory()->create(['role' => 'player']);

    $this->actingAs($user);

    Volt::test('umpire.events.index')->assertForbidden();
});

test('only tournaments with a match assigned to the current umpire are listed', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $otherUmpire = User::factory()->create(['role' => 'umpire']);

    $myTournament = umpireEventsTournament(['name' => 'Inato Tournament']);
    $myTournamentCategory = umpireEventsTournamentCategory($myTournament);
    Matches::create([
        'tournament_category_id' => $myTournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 1',
        'assigned_umpire_id' => $umpire->id,
    ]);

    $otherTournament = umpireEventsTournament(['name' => 'Barangay Cup']);
    $otherTournamentCategory = umpireEventsTournamentCategory($otherTournament);
    Matches::create([
        'tournament_category_id' => $otherTournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 2',
        'assigned_umpire_id' => $otherUmpire->id,
    ]);

    $this->actingAs($umpire);

    Volt::test('umpire.events.index')
        ->assertSee('Inato Tournament')
        ->assertDontSee('Barangay Cup');
});
