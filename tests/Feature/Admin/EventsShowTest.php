<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function eventsShowTournament(array $overrides = []): Tournament
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

function eventsShowCategory(Tournament $tournament, array $categoryOverrides = [], array $tournamentCategoryOverrides = []): TournamentCategory
{
    $category = Category::create(array_merge([
        'name' => "Men's Doubles",
        'level' => 'Beginner',
        'gender' => 'male',
        'age_min' => 12,
        'age_max' => 25,
        'team_size' => 2,
    ], $categoryOverrides));

    return TournamentCategory::create(array_merge([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 300,
        'max_slots' => 20,
        'status' => 'open',
    ], $tournamentCategoryOverrides));
}

function eventsShowAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('the route resolves the tournament via route model binding', function () {
    $tournament = eventsShowTournament();

    $this->actingAs(eventsShowAdmin())
        ->get(route('admin.events.show', $tournament))
        ->assertOk()
        ->assertSee($tournament->name);
});

test('a non-admin cannot view the event detail page', function () {
    $tournament = eventsShowTournament();

    $this->actingAs(User::factory()->create(['role' => 'player']));

    Volt::test('admin.events.show', ['tournament' => $tournament])->assertForbidden();
});

test('it shows the category label, level, and age range', function () {
    $tournament = eventsShowTournament();
    eventsShowCategory($tournament);

    $this->actingAs(eventsShowAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->assertSee("Men's Doubles")
        ->assertSee('Beginner')
        ->assertSee('12')
        ->assertSee('25');
});

test('stats only count pending and approved records for this tournament', function () {
    $tournament = eventsShowTournament();
    $tournamentCategory = eventsShowCategory($tournament);

    $otherTournament = eventsShowTournament(['name' => 'Barangay Cup']);
    $otherCategory = eventsShowCategory($otherTournament);

    $pendingPlayer = Player::create(['name' => 'Marcus Villanueva', 'level' => 'Beginner']);
    $pendingApplication = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'pending_verification']);
    ApplicationPlayer::create(['application_id' => $pendingApplication->id, 'player_id' => $pendingPlayer->id, 'level_at_apply' => 'Beginner']);

    $approvedPlayer = Player::create(['name' => 'Ella Bautista', 'level' => 'Beginner']);
    $approvedApplication = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
    ApplicationPlayer::create(['application_id' => $approvedApplication->id, 'player_id' => $approvedPlayer->id, 'level_at_apply' => 'Beginner']);

    PaymentDetail::create([
        'application_id' => $approvedApplication->id,
        'date_of_payment_made' => now(),
        'reference_number' => '1234567890',
        'payment_status' => 'verified',
        'proof_of_payment' => 'payment-proofs/demo.png',
    ]);

    // Noise from an unrelated tournament that must not leak into these totals.
    $otherPlayer = Player::create(['name' => 'Other Tournament Player', 'level' => 'Beginner']);
    $otherApplication = PlayerApplication::create(['tournament_category_id' => $otherCategory->id, 'status' => 'pending_verification']);
    ApplicationPlayer::create(['application_id' => $otherApplication->id, 'player_id' => $otherPlayer->id, 'level_at_apply' => 'Beginner']);

    $this->actingAs(eventsShowAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->assertSet('totals.categories', 1)
        ->assertSet('totals.pendingApplications', 1)
        ->assertSet('totals.registeredPlayers', 1)
        ->assertDontSee('Other Tournament Player');
});

test('publish toggle flips the tournament status', function () {
    $tournament = eventsShowTournament(['status' => 'draft']);
    eventsShowCategory($tournament);

    $this->actingAs(eventsShowAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('togglePublish')
        ->assertSee('Unpublish Event');

    expect($tournament->fresh()->status)->toBe('published');
});

test('publishing a draft with no categories is rejected', function () {
    $tournament = eventsShowTournament(['status' => 'draft']);

    $this->actingAs(eventsShowAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('togglePublish')
        ->assertDispatched('system-message', variant: 'danger')
        ->assertSee('Publish Event');

    expect($tournament->fresh()->status)->toBe('draft');
});

test('a draft with no categories shows an Add Categories button, a published event does not', function () {
    $draft = eventsShowTournament(['name' => 'Draft Event', 'status' => 'draft']);
    $published = eventsShowTournament(['name' => 'Published Event', 'status' => 'published']);

    $this->actingAs(eventsShowAdmin());

    Volt::test('admin.events.show', ['tournament' => $draft])
        ->assertSee('No Categories Added Yet')
        ->assertSeeHtml('wire:click="openCategoryModal"');

    Volt::test('admin.events.show', ['tournament' => $published])
        ->assertSee('No Categories Added Yet')
        ->assertDontSeeHtml('wire:click="openCategoryModal"');
});

test('the Edit Event button only shows for a draft event', function () {
    $draft = eventsShowTournament(['name' => 'Draft Event', 'status' => 'draft']);
    $published = eventsShowTournament(['name' => 'Published Event', 'status' => 'published']);

    $this->actingAs(eventsShowAdmin());

    Volt::test('admin.events.show', ['tournament' => $draft])
        ->assertSeeHtml(route('admin.events.edit', $draft));

    Volt::test('admin.events.show', ['tournament' => $published])
        ->assertDontSeeHtml(route('admin.events.edit', $published));
});
