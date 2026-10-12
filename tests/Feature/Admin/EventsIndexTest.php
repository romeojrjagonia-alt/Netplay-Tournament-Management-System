<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function eventsIndexTournament(array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
        'name' => 'Inato Tournament',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(20)->toDateString(),
        'date_end' => now()->addDays(21)->toDateString(),
        'registration_start' => '2026-06-01',
        'registration_end' => now()->addDays(10)->toDateString(),
        'status' => 'published',
    ], $overrides));
}

function eventsIndexCategory(Tournament $tournament, array $tournamentCategoryOverrides = []): TournamentCategory
{
    $category = Category::create([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
    ]);

    return TournamentCategory::create(array_merge([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 20,
        'status' => 'open',
    ], $tournamentCategoryOverrides));
}

function eventsIndexAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('the index route renders for an admin', function () {
    eventsIndexTournament();

    $this->actingAs(eventsIndexAdmin())
        ->get(route('admin.events.index'))
        ->assertOk();
});

test('a non-admin cannot view the events index', function () {
    $this->actingAs(User::factory()->create(['role' => 'player']));

    Volt::test('admin.events.index')->assertForbidden();
});

test('the all tab lists draft, published, and concluded tournaments', function () {
    eventsIndexTournament(['name' => 'Draft Tournament', 'status' => 'draft']);
    eventsIndexTournament(['name' => 'Published Tournament', 'status' => 'published']);
    eventsIndexTournament(['name' => 'Concluded Tournament', 'status' => 'published', 'date_end' => '2026-01-01']);

    $this->actingAs(eventsIndexAdmin());

    Volt::test('admin.events.index')
        ->assertSee('Draft Tournament')
        ->assertSee('Published Tournament')
        ->assertSee('Concluded Tournament');
});

test('the published tab excludes drafts and concluded tournaments', function () {
    eventsIndexTournament(['name' => 'Draft Tournament', 'status' => 'draft']);
    eventsIndexTournament(['name' => 'Published Tournament', 'status' => 'published']);
    eventsIndexTournament(['name' => 'Concluded Tournament', 'status' => 'published', 'date_end' => '2026-01-01']);

    $this->actingAs(eventsIndexAdmin());

    Volt::test('admin.events.index')
        ->set('tab', 'published')
        ->assertDontSee('Draft Tournament')
        ->assertSee('Published Tournament')
        ->assertDontSee('Concluded Tournament');
});

test('the concluded tab only lists published tournaments whose date range has passed', function () {
    eventsIndexTournament(['name' => 'Published Tournament', 'status' => 'published']);
    eventsIndexTournament(['name' => 'Concluded Tournament', 'status' => 'published', 'date_end' => '2026-01-01']);

    $this->actingAs(eventsIndexAdmin());

    Volt::test('admin.events.index')
        ->set('tab', 'concluded')
        ->assertDontSee('Published Tournament')
        ->assertSee('Concluded Tournament');
});

test('search filters tournaments by name', function () {
    eventsIndexTournament(['name' => 'Inato Tournament']);
    eventsIndexTournament(['name' => 'Barangay Cup']);

    $this->actingAs(eventsIndexAdmin());

    Volt::test('admin.events.index')
        ->set('search', 'Inato')
        ->assertSee('Inato Tournament')
        ->assertDontSee('Barangay Cup');
});

test('remaining slots count every non-rejected entry while registered players count approved ones', function () {
    $tournament = eventsIndexTournament();
    $tournamentCategory = eventsIndexCategory($tournament, ['max_slots' => 10]);

    $approvedPlayer = Player::create(['name' => 'Marcus Villanueva', 'level' => 'Beginner']);
    $approvedApplication = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
    ApplicationPlayer::create(['application_id' => $approvedApplication->id, 'player_id' => $approvedPlayer->id, 'level_at_apply' => 'Beginner']);

    $pendingPlayer = Player::create(['name' => 'Ella Bautista', 'level' => 'Beginner']);
    $pendingApplication = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'pending_verification']);
    ApplicationPlayer::create(['application_id' => $pendingApplication->id, 'player_id' => $pendingPlayer->id, 'level_at_apply' => 'Beginner']);

    $this->actingAs(eventsIndexAdmin());

    // 10 slots, 1 approved + 1 pending entry -> 8 remaining; only the approved player counts as registered.
    Volt::test('admin.events.index')
        ->assertSeeHtml('text-4xl font-bold text-white">8<')
        ->assertSeeHtml('text-4xl font-bold text-white">1<');
});
