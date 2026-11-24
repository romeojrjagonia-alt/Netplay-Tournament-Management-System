<?php

use App\Models\Category;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function officialsEvent(): array
{
    $tournament = Tournament::create([
        'name' => 'Officials Open',
        'format' => 'round_robin',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-10-20',
        'date_end' => '2026-10-22',
        'registration_start' => '2026-10-01',
        'registration_end' => '2026-10-18',
        'status' => 'published',
    ]);

    $category = Category::create(['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1, 'level' => 'Level C']);

    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 16,
        'status' => 'open',
    ]);

    return [$tournament, $tournamentCategory];
}

function officialsMatch(TournamentCategory $tournamentCategory, int $position, string $status = 'scheduled', ?int $umpireId = null): Matches
{
    return Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'round_robin',
        'round' => 1,
        'position' => $position,
        'status' => $status,
        'assigned_umpire_id' => $umpireId,
    ]);
}

function officialsPage(Tournament $tournament, ?User $user = null)
{
    return Volt::actingAs($user ?? User::factory()->create(['role' => 'admin']))
        ->test('admin.events.officials', ['tournament' => $tournament]);
}

it('assigns an umpire to every unplayed match in a category', function () {
    [$tournament, $tournamentCategory] = officialsEvent();
    $umpire = User::factory()->create(['role' => 'umpire', 'name' => 'Pedro Reyes']);

    $open = officialsMatch($tournamentCategory, 1);
    $inProgress = officialsMatch($tournamentCategory, 2, 'in_progress');
    $played = officialsMatch($tournamentCategory, 3, 'completed');

    officialsPage($tournament)
        ->set("umpireByCategory.{$tournamentCategory->id}", (string) $umpire->id)
        ->call('assignToCategory', $tournamentCategory->id)
        ->assertHasNoErrors()
        ->assertDispatched('system-message', variant: 'success');

    expect($open->fresh()->assigned_umpire_id)->toBe($umpire->id)
        ->and($inProgress->fresh()->assigned_umpire_id)->toBe($umpire->id)
        ->and($played->fresh()->assigned_umpire_id)->toBeNull();
});

it('can clear the umpire from a category', function () {
    [$tournament, $tournamentCategory] = officialsEvent();
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = officialsMatch($tournamentCategory, 1, 'scheduled', $umpire->id);

    officialsPage($tournament)
        ->set("umpireByCategory.{$tournamentCategory->id}", '')
        ->call('assignToCategory', $tournamentCategory->id)
        ->assertDispatched('system-message', variant: 'success');

    expect($match->fresh()->assigned_umpire_id)->toBeNull();
});

it('only accepts umpire accounts', function () {
    [$tournament, $tournamentCategory] = officialsEvent();
    $player = User::factory()->create(['role' => 'player']);
    $match = officialsMatch($tournamentCategory, 1);

    officialsPage($tournament)
        ->set("umpireByCategory.{$tournamentCategory->id}", (string) $player->id)
        ->call('assignToCategory', $tournamentCategory->id)
        ->assertHasErrors();

    expect($match->fresh()->assigned_umpire_id)->toBeNull();
});

it('explains when a category has no schedule yet', function () {
    [$tournament, $tournamentCategory] = officialsEvent();
    $umpire = User::factory()->create(['role' => 'umpire']);

    officialsPage($tournament)
        ->assertSee('No schedule yet')
        ->set("umpireByCategory.{$tournamentCategory->id}", (string) $umpire->id)
        ->call('assignToCategory', $tournamentCategory->id)
        ->assertDispatched('system-message', variant: 'danger');
});

it('shows each umpire their load in the event', function () {
    [$tournament, $tournamentCategory] = officialsEvent();
    $umpire = User::factory()->create(['role' => 'umpire', 'name' => 'Pedro Reyes']);

    officialsMatch($tournamentCategory, 1, 'scheduled', $umpire->id);
    officialsMatch($tournamentCategory, 2, 'completed', $umpire->id);

    $component = officialsPage($tournament)->assertSee('Pedro Reyes');

    $row = $component->get('umpires')->firstWhere(fn ($row) => $row['user']->is($umpire));

    expect($row['assigned'])->toBe(2)->and($row['completed'])->toBe(1);
});

it('keeps the page away from non-admins and links it from the event tabs', function () {
    [$tournament] = officialsEvent();

    officialsPage($tournament, User::factory()->create(['role' => 'umpire']))->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.events.officials', $tournament))
        ->assertOk()
        ->assertSee(route('admin.events.officials', $tournament), false);
});
