<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Officials\OfficialConflicts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

function conflictCategory(string $name = 'Conflict Cup'): TournamentCategory
{
    $tournament = Tournament::create([
        'name' => $name,
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(20)->toDateString(),
        'date_end' => now()->addDays(21)->toDateString(),
        'registration_start' => now()->subDay()->toDateString(),
        'registration_end' => now()->addDays(10)->toDateString(),
        'status' => 'published',
    ]);

    $category = Category::create(['name' => "Men's Singles ".$name, 'gender' => 'male', 'team_size' => 1]);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 150,
        'max_slots' => 32,
        'status' => 'open',
    ]);
}

function conflictMatch(TournamentCategory $tournamentCategory, ?User $umpire = null): Matches
{
    return Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDays(20),
        'court' => 'Court 1',
        'assigned_umpire_id' => $umpire?->id,
    ]);
}

/**
 * Enter the user's own player record in the category.
 */
function conflictEnter(User $user, TournamentCategory $tournamentCategory, string $status = 'approved'): PlayerApplication
{
    $player = $user->ensurePlayer();
    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => $status]);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);

    return $application;
}

function conflictAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('an umpire cannot start registering for a tournament they officiate in', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    conflictMatch($category, $umpire);

    $this->actingAs($umpire)->withSession([User::PLAYER_MODE_SESSION_KEY => true]);

    Volt::test('player.events')
        ->call('viewTournament', $category->tournament_id)
        ->call('startRegistration')
        ->assertDispatched('system-message', title: 'Cannot register', message: OfficialConflicts::officiatingMessage(), variant: 'danger')
        ->assertNotDispatched('modal-show');
});

test('an umpire cannot submit a registration for a tournament they officiate in', function () {
    Storage::fake('public');

    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    $other = conflictCategory('Other Cup');
    conflictMatch($category, $umpire);

    $this->actingAs($umpire)->withSession([User::PLAYER_MODE_SESSION_KEY => true]);

    // Open the form for a tournament they are free to enter, then point it at the conflicted one.
    Volt::test('player.events')
        ->call('viewTournament', $other->tournament_id)
        ->call('startRegistration')
        ->set('viewingTournamentId', $category->tournament_id)
        ->set('regTournamentCategoryId', $category->id)
        ->set('ownDocumentType', 'national_id')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('submitRegistration')
        ->assertDispatched('system-message', title: 'Cannot register');

    expect(PlayerApplication::where('tournament_category_id', $category->id)->count())->toBe(0);
});

test('an umpire can still register for tournaments they do not officiate in', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    $other = conflictCategory('Free Cup');
    conflictMatch($category, $umpire);

    $this->actingAs($umpire)->withSession([User::PLAYER_MODE_SESSION_KEY => true]);

    Volt::test('player.events')
        ->call('viewTournament', $other->tournament_id)
        ->call('startRegistration')
        ->assertNotDispatched('system-message')
        ->assertDispatched('modal-show', name: 'tournament-registration');
});

test('the landing page registration modal will not open for an umpire who officiates in the tournament', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    conflictMatch($category, $umpire);

    $this->actingAs($umpire);

    Volt::test('tournaments.register')
        ->dispatch('open-guest-registration', tournamentId: $category->tournament_id)
        ->assertSet('tournament', null)
        ->assertNotDispatched('modal-show')
        ->assertDispatched('system-message', title: 'Cannot register');
});

test('a guest and other tournaments are not affected by the landing page check', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    $other = conflictCategory('Free Cup');
    conflictMatch($category, $umpire);

    // A guest has no account to officiate with.
    Volt::test('tournaments.register')
        ->dispatch('open-guest-registration', tournamentId: $category->tournament_id)
        ->assertSet('tournament.id', $category->tournament_id)
        ->assertDispatched('modal-show');

    $this->actingAs($umpire);

    Volt::test('tournaments.register')
        ->dispatch('open-guest-registration', tournamentId: $other->tournament_id)
        ->assertSet('tournament.id', $other->tournament_id);
});

test('a partner who officiates in the tournament cannot be picked', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    conflictMatch($category, $umpire);
    $partner = $umpire->ensurePlayer();

    Volt::test('tournaments.register', ['tournament' => $category->tournament])
        ->call('selectPairPlayer', $partner->id)
        ->assertSet('pairPlayerId', null)
        ->assertDispatched('system-message', title: 'Cannot pick this partner');

    $walkIn = Player::create(['name' => 'Free Agent']);

    Volt::test('tournaments.register', ['tournament' => $category->tournament])
        ->call('selectPairPlayer', $walkIn->id)
        ->assertSet('pairPlayerId', $walkIn->id);
});

test('an umpire who is entered in the tournament cannot be assigned to its matches from the schedule page', function () {
    $umpire = User::factory()->create(['role' => 'umpire', 'name' => 'Gwen Domine']);
    $category = conflictCategory();
    $match = conflictMatch($category);
    conflictEnter($umpire, $category);

    $this->actingAs(conflictAdmin());

    Volt::test('admin.events.schedule.show', ['tournament' => $category->tournament, 'tournamentCategory' => $category])
        ->call('editMatch', $match->id)
        ->set('editUmpireId', $umpire->id)
        ->call('saveMatch')
        ->assertHasErrors('editUmpireId')
        ->assertSee('Gwen Domine is entered in this tournament as a player');

    expect($match->fresh()->assigned_umpire_id)->toBeNull();
});

test('an umpire who is entered in another category of the tournament is also blocked', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    $second = TournamentCategory::create([
        'tournament_id' => $category->tournament_id,
        'category_id' => Category::create(['name' => "Women's Singles", 'gender' => 'female', 'team_size' => 1])->id,
        'registration_fee' => 150,
        'max_slots' => 32,
        'status' => 'open',
    ]);
    $match = conflictMatch($category);
    conflictEnter($umpire, $second);

    $this->actingAs(conflictAdmin());

    Volt::test('admin.events.schedule.show', ['tournament' => $category->tournament, 'tournamentCategory' => $category])
        ->call('editMatch', $match->id)
        ->set('editUmpireId', $umpire->id)
        ->call('saveMatch')
        ->assertHasErrors('editUmpireId');
});

test('a rejected application does not stop an umpire from being assigned', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    $match = conflictMatch($category);
    conflictEnter($umpire, $category, 'rejected');

    $this->actingAs(conflictAdmin());

    Volt::test('admin.events.schedule.show', ['tournament' => $category->tournament, 'tournamentCategory' => $category])
        ->call('editMatch', $match->id)
        ->set('editUmpireId', $umpire->id)
        ->call('saveMatch')
        ->assertHasNoErrors();

    expect($match->fresh()->assigned_umpire_id)->toBe($umpire->id);
});

test('an umpire who is not entered can be assigned, and the dropdown flags those who are', function () {
    $free = User::factory()->create(['role' => 'umpire', 'name' => 'Free Umpire']);
    $entered = User::factory()->create(['role' => 'umpire', 'name' => 'Entered Umpire']);
    $category = conflictCategory();
    $match = conflictMatch($category);
    conflictEnter($entered, $category);

    $this->actingAs(conflictAdmin());

    $component = Volt::test('admin.events.schedule.show', ['tournament' => $category->tournament, 'tournamentCategory' => $category])
        ->call('editMatch', $match->id)
        ->assertSee('Entered Umpire')
        ->assertSee('(entered as a player)')
        ->set('editUmpireId', $free->id)
        ->call('saveMatch')
        ->assertHasNoErrors();

    expect($match->fresh()->assigned_umpire_id)->toBe($free->id);
});

test('the officials page will not assign an entered umpire to a whole category', function () {
    $umpire = User::factory()->create(['role' => 'umpire', 'name' => 'Gwen Domine']);
    $category = conflictCategory();
    $match = conflictMatch($category);
    conflictEnter($umpire, $category);

    $this->actingAs(conflictAdmin());

    Volt::test('admin.events.officials', ['tournament' => $category->tournament])
        ->assertSee('(entered as a player)')
        ->set("umpireByCategory.{$category->id}", (string) $umpire->id)
        ->call('assignToCategory', $category->id)
        ->assertDispatched('system-message', title: 'Cannot assign');

    expect($match->fresh()->assigned_umpire_id)->toBeNull();
});

test('the officials page still assigns an umpire who is free', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    $match = conflictMatch($category);

    $this->actingAs(conflictAdmin());

    Volt::test('admin.events.officials', ['tournament' => $category->tournament])
        ->set("umpireByCategory.{$category->id}", (string) $umpire->id)
        ->call('assignToCategory', $category->id)
        ->assertDispatched('system-message', title: 'Officials updated');

    expect($match->fresh()->assigned_umpire_id)->toBe($umpire->id);
});

test('the conflict checks look at the right tournament and ignore rejected entries', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = conflictCategory();
    $other = conflictCategory('Other Cup');

    conflictMatch($category, $umpire);
    conflictEnter($umpire, $other);
    conflictEnter($umpire, $category, 'rejected');

    expect(OfficialConflicts::officiatesIn($umpire, $category->tournament))->toBeTrue()
        ->and(OfficialConflicts::officiatesIn($umpire, $other->tournament))->toBeFalse()
        ->and(OfficialConflicts::isEnteredIn($umpire, $other->tournament))->toBeTrue()
        ->and(OfficialConflicts::isEnteredIn($umpire, $category->tournament))->toBeFalse()
        ->and(OfficialConflicts::enteredUmpireIds($other->tournament))->toBe([$umpire->id])
        ->and(OfficialConflicts::enteredUmpireIds($category->tournament))->toBe([]);
});
