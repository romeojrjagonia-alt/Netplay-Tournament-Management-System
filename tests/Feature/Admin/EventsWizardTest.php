<?php

use App\Models\Category;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    Volt::test('admin.events.wizard.review', ['tournament' => $tournament])->assertForbidden();
});

test('completing step 1 creates a draft tournament and moves to the confirmation step', function () {
    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details')
        ->set('name', 'Inato Tournament')
        ->set('format', 'round_robin')
        ->set('scoringSystem', 'Best of 3 to 21')
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
        ->assertRedirect(route('admin.events.wizard.review', $tournament));

    expect(Tournament::count())->toBe(1);
    expect($tournament->fresh()->name)->toBe('Renamed Tournament');
});

test('completing step 1 with a time range stores it as a formatted string', function () {
    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details')
        ->set('name', 'Inato Tournament')
        ->set('format', 'round_robin')
        ->set('scoringSystem', 'Best of 3 to 21')
        ->set('dateStart', '2026-09-01')
        ->set('dateEnd', '2026-09-30')
        ->set('timeStart', '08:00')
        ->set('timeEnd', '11:00')
        ->set('registrationStart', '2026-06-01')
        ->set('registrationEnd', '2026-08-31')
        ->set('selectedDays', ['Sat'])
        ->call('next')
        ->assertRedirect();

    $tournament = Tournament::firstWhere('name', 'Inato Tournament');

    expect($tournament->time)->toBe('8:00 AM - 11:00 AM');
});

test('an end time before the start time is rejected', function () {
    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details')
        ->set('name', 'Inato Tournament')
        ->set('format', 'round_robin')
        ->set('scoringSystem', 'Best of 3 to 21')
        ->set('dateStart', '2026-09-01')
        ->set('dateEnd', '2026-09-30')
        ->set('timeStart', '11:00')
        ->set('timeEnd', '08:00')
        ->set('registrationStart', '2026-06-01')
        ->set('registrationEnd', '2026-08-31')
        ->set('selectedDays', ['Sat'])
        ->call('next')
        ->assertHasErrors(['timeEnd' => 'after']);
});

test('editing a draft with an existing time range prefills the start and end inputs', function () {
    $tournament = wizardDraftTournament(['time' => '8:00 AM - 11:00 AM']);

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details', ['tournament' => $tournament])
        ->assertSet('timeStart', '08:00')
        ->assertSet('timeEnd', '11:00');
});

test('replacing the cover image deletes the old file from disk', function () {
    Storage::fake('public');

    $tournament = wizardDraftTournament(['poster_path' => 'tournament-posters/old.jpg']);
    Storage::disk('public')->put('tournament-posters/old.jpg', 'fake-image-contents');

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details', ['tournament' => $tournament])
        ->set('poster', UploadedFile::fake()->create('new-cover.jpg', 10, 'image/jpeg'))
        ->call('next')
        ->assertRedirect();

    Storage::disk('public')->assertMissing('tournament-posters/old.jpg');
    expect($tournament->fresh()->poster_path)->not->toBe('tournament-posters/old.jpg');
});

test('step 1 requires a cover image to actually be an image', function () {
    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details')
        ->set('name', 'Inato Tournament')
        ->set('format', 'round_robin')
        ->set('scoringSystem', 'Best of 3 to 21')
        ->set('dateStart', '2026-09-01')
        ->set('dateEnd', '2026-09-30')
        ->set('registrationStart', '2026-06-01')
        ->set('registrationEnd', '2026-08-31')
        ->set('selectedDays', ['Sat'])
        ->set('poster', UploadedFile::fake()->create('not-an-image.pdf', 100))
        ->call('next')
        ->assertHasErrors(['poster' => 'image']);
});

test('the confirmation step shows the event recap and the post-confirm prompt', function () {
    $tournament = wizardDraftTournament();

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.review', ['tournament' => $tournament])
        ->assertSee('Inato Tournament')
        ->assertSee('Best of 3 to 21')
        ->assertSet('showCategoryPrompt', false)
        ->call('confirm')
        ->assertSet('showCategoryPrompt', true)
        ->assertSee('Add Later')
        ->call('addCategories')
        ->assertRedirect(route('admin.categories.index'));
});

test('choosing Add Later from the confirmation prompt goes to the event page', function () {
    $tournament = wizardDraftTournament();

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.review', ['tournament' => $tournament])
        ->call('confirm')
        ->call('addLater')
        ->assertRedirect(route('admin.events.show', $tournament));
});

test('step 1 requires a pointing system from the supported list', function () {
    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.wizard.details')
        ->set('name', 'Inato Tournament')
        ->set('format', 'round_robin')
        ->set('scoringSystem', 'Made up')
        ->set('dateStart', '2026-09-01')
        ->set('dateEnd', '2026-09-30')
        ->set('registrationStart', '2026-06-01')
        ->set('registrationEnd', '2026-08-31')
        ->set('selectedDays', ['Sat'])
        ->call('next')
        ->assertHasErrors('scoringSystem');
});

test('the edit page saves changes and redirects back to the event page', function () {
    $tournament = wizardDraftTournament(['name' => 'Old Name']);

    $this->actingAs(wizardAdmin());

    Volt::test('admin.events.edit', ['tournament' => $tournament])
        ->assertSee('Save Changes')
        ->assertSee('Edit Categories')
        ->set('name', 'Renamed via Edit')
        ->call('save')
        ->assertRedirect(route('admin.events.show', $tournament));

    expect($tournament->fresh()->name)->toBe('Renamed via Edit');
});

test('a non-admin cannot access the edit page', function () {
    $tournament = wizardDraftTournament();

    $this->actingAs(User::factory()->create(['role' => 'player']));

    Volt::test('admin.events.edit', ['tournament' => $tournament])->assertForbidden();
});

test('a published event cannot be reached through the details, edit, or review routes', function () {
    $tournament = wizardDraftTournament(['status' => 'published']);
    wizardAddCategory($tournament);

    $admin = wizardAdmin();

    $this->actingAs($admin)->get(route('admin.events.wizard.details', $tournament))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.events.edit', $tournament))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.events.wizard.review', $tournament))->assertForbidden();
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
    $this->actingAs($admin)->get(route('admin.events.wizard.review', $tournament))->assertOk();
    $this->actingAs($admin)->get(route('admin.events.edit', $tournament))->assertOk();
});

test('double elimination is not offered as a tournament format', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Volt::test('admin.events.wizard.details')
        ->assertDontSee('Double Elimination')
        ->assertSee('Single Elimination')
        ->assertSee('Round Robin')
        ->set('format', 'double_elimination')
        ->call('next')
        ->assertHasErrors(['format']);
});
