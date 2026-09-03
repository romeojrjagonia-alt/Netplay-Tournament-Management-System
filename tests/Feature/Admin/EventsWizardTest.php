<?php

use App\Models\Category;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;

function wizardAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function wizardDraftTournament(array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
        'name' => 'Inato Tournament',
        'format' => 'round_robin',
        'sport' => 'Badminton',
        'location' => 'OBC Badminton Facility',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-09-01',
        'date_end' => '2026-09-30',
        'time' => '9:00 AM - 5:00 PM',
        'specific_days' => 'Sat, Sun',
        'registration_start' => '2026-06-01',
        'registration_end' => '2026-12-31',
        'status' => 'draft',
    ], $overrides));
}

function wizardAddCategory(Tournament $tournament, array $overrides = []): TournamentCategory
{
    $category = Category::create(array_merge([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
        'level' => 'Beginner',
        'age_min' => 12,
        'age_max' => 25,
    ], $overrides['category'] ?? []));

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => $overrides['registration_fee'] ?? 250,
        'max_slots' => $overrides['max_slots'] ?? 20,
        'status' => 'open',
    ]);
}

test('a non-admin cannot access any wizard step', function () {
    $tournament = wizardDraftTournament();

    $this->actingAs(User::factory()->create(['role' => 'player']));

    Volt::test('admin.events.wizard.details')->assertForbidden();
    Volt::test('admin.events.wizard.categories', ['tournament' => $tournament])->assertForbidden();
    Volt::test('admin.events.wizard.review', ['tournament' => $tournament])->assertForbidden();
});

test('completing step 1 creates a draft tournament and moves to step 2', function () {
    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details')
        ->set('name', 'Inato Tournament')
        ->set('format', 'round_robin')
        ->set('location', 'OBC Badminton Facility')
        ->set('dateStart', '2026-09-01')
        ->set('dateEnd', '2026-09-30')
        ->set('registrationStart', '2026-06-01')
        ->set('registrationEnd', '2026-08-31')
        ->set('selectedDays', ['Sat', 'Sun'])
        ->call('next')
        ->assertRedirect();

    $tournament = Tournament::firstWhere('name', 'Inato Tournament');

    expect($tournament)->not->toBeNull();
    expect($tournament->status)->toBe('draft');
    expect($tournament->specific_days)->toBe('Sun, Sat');
});

test('step 1 in edit mode updates the existing draft instead of creating a new one', function () {
    $tournament = wizardDraftTournament(['name' => 'Old Name']);

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details', ['tournament' => $tournament])
        ->set('name', 'Renamed Tournament')
        ->call('next')
        ->assertRedirect(route('admin.events.wizard.categories', $tournament));

    expect(Tournament::count())->toBe(1);
    expect($tournament->fresh()->name)->toBe('Renamed Tournament');
});

test('step 1 requires a cover image to actually be an image', function () {
    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details')
        ->set('name', 'Inato Tournament')
        ->set('format', 'round_robin')
        ->set('dateStart', '2026-09-01')
        ->set('dateEnd', '2026-09-30')
        ->set('registrationStart', '2026-06-01')
        ->set('registrationEnd', '2026-08-31')
        ->set('selectedDays', ['Sat'])
        ->set('poster', UploadedFile::fake()->create('not-an-image.pdf', 100))
        ->call('next')
        ->assertHasErrors(['poster' => 'image']);
});

test('adding a category creates the category and tournament category rows', function () {
    $tournament = wizardDraftTournament();

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.categories', ['tournament' => $tournament])
        ->set('categoryPreset', 'mixed_doubles')
        ->set('level', 'Level B')
        ->set('ageMin', '18')
        ->set('ageMax', '25')
        ->set('fee', '300')
        ->set('limit', '16')
        ->call('addCategory')
        ->assertSee('Mixed Doubles')
        ->assertSee('Level B');

    $tournamentCategory = $tournament->tournamentCategories()->with('category')->first();

    expect($tournamentCategory)->not->toBeNull();
    expect($tournamentCategory->category->gender)->toBe('mixed');
    expect($tournamentCategory->category->team_size)->toBe(2);
    expect((float) $tournamentCategory->registration_fee)->toBe(300.0);
    expect($tournamentCategory->max_slots)->toBe(16);
});

test('adding the same category twice to the same tournament is rejected', function () {
    $tournament = wizardDraftTournament();
    wizardAddCategory($tournament, ['category' => ['name' => 'Mixed Doubles', 'gender' => 'mixed', 'team_size' => 2, 'level' => 'Level B', 'age_min' => 18, 'age_max' => 25]]);

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.categories', ['tournament' => $tournament])
        ->set('categoryPreset', 'mixed_doubles')
        ->set('level', 'Level B')
        ->set('ageMin', '18')
        ->set('ageMax', '25')
        ->set('fee', '300')
        ->set('limit', '16')
        ->call('addCategory')
        ->assertHasErrors('categoryPreset');

    expect($tournament->tournamentCategories()->count())->toBe(1);
});

test('removing a category deletes it from the event', function () {
    $tournament = wizardDraftTournament();
    $tournamentCategory = wizardAddCategory($tournament);

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.categories', ['tournament' => $tournament])
        ->call('removeCategory', $tournamentCategory->id);

    expect($tournament->tournamentCategories()->count())->toBe(0);
});

test('step 2 requires at least one category before continuing', function () {
    $tournament = wizardDraftTournament();

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.categories', ['tournament' => $tournament])
        ->call('next')
        ->assertHasErrors('categoryPreset');
});

test('step 2 advances to step 3 once a category exists', function () {
    $tournament = wizardDraftTournament();
    wizardAddCategory($tournament);

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.categories', ['tournament' => $tournament])
        ->call('next')
        ->assertRedirect(route('admin.events.wizard.review', $tournament));
});

test('the review step shows the event and category recap', function () {
    $tournament = wizardDraftTournament();
    wizardAddCategory($tournament, ['registration_fee' => 250, 'max_slots' => 20]);

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.review', ['tournament' => $tournament])
        ->assertSee('Inato Tournament')
        ->assertSee("Men's Singles")
        ->assertSee('PHP 250.00')
        ->assertSeeHtml(route('admin.events.show', $tournament));
});

test('the create-event route resolves the details wizard without a tournament', function () {
    $this->actingAs(wizardAdmin())
        ->get(route('admin.events.create'))
        ->assertOk();
});

test('the wizard step routes resolve the tournament via route model binding', function () {
    $tournament = wizardDraftTournament();
    wizardAddCategory($tournament);

    $admin = wizardAdmin();

    $this->actingAs($admin)->get(route('admin.events.wizard.details', $tournament))->assertOk();
    $this->actingAs($admin)->get(route('admin.events.wizard.categories', $tournament))->assertOk();
    $this->actingAs($admin)->get(route('admin.events.wizard.review', $tournament))->assertOk();
});
