<?php

use App\Models\Category;
use App\Models\Matches;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

function roleSwitchMatch(User $umpire, string $status): Matches
{
    $tournament = Tournament::create([
        'name' => 'Switch Cup '.$status,
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-10-20',
        'date_end' => '2026-10-22',
        'registration_start' => '2026-10-01',
        'registration_end' => '2026-10-18',
        'status' => 'published',
    ]);

    $category = Category::create(['name' => "Men's Singles ".$status, 'gender' => 'male', 'team_size' => 1]);

    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);

    return Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => $status,
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 1',
        'assigned_umpire_id' => $umpire->id,
    ]);
}

test('only umpires see the role switch in the sidebar', function () {
    $this->actingAs(User::factory()->create(['role' => 'umpire']))
        ->get(route('umpire.dashboard'))
        ->assertOk()
        ->assertSee('Switch to Player');

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Switch to Player');

    $this->actingAs(User::factory()->create(['role' => 'player']))
        ->get(route('player.dashboard'))
        ->assertOk()
        ->assertDontSee('Switch to Player')
        ->assertDontSee('Switch to Umpire');
});

test('an umpire can switch to the player side without their stored role changing', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);

    $this->actingAs($umpire);

    Volt::test('account.role-switch')
        ->call('switchRole')
        ->assertRedirect(route('player.dashboard'));

    expect($umpire->fresh()->role)->toBe('umpire')
        ->and(session(User::PLAYER_MODE_SESSION_KEY))->toBeTrue()
        ->and($umpire->fresh()->player)->not->toBeNull();

    $this->get(route('player.dashboard'))->assertOk()->assertSee('Switch to Umpire')->assertDontSee('Switch to Player');
    $this->get(route('player.events'))->assertOk();
});

test('while acting as a player the umpire pages are closed and they can switch back', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);

    $this->actingAs($umpire)->withSession([User::PLAYER_MODE_SESSION_KEY => true]);

    $this->get(route('umpire.dashboard'))->assertRedirect(route('player.dashboard'));
    $this->get(route('umpire.matches'))->assertRedirect(route('player.dashboard'));
    $this->get('/dashboard')->assertRedirect(route('player.dashboard'));

    Volt::test('account.role-switch')
        ->call('switchRole')
        ->assertRedirect(route('umpire.dashboard'));

    expect(session()->has(User::PLAYER_MODE_SESSION_KEY))->toBeFalse()
        ->and($umpire->fresh()->role)->toBe('umpire');

    $this->get(route('umpire.dashboard'))->assertOk();
    $this->get(route('player.dashboard'))->assertRedirect(route('umpire.dashboard'));
});

test('the umpire keeps their assigned matches while they are on the player side', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = roleSwitchMatch($umpire, 'scheduled');

    $this->actingAs($umpire);

    Volt::test('account.role-switch')->call('switchRole');

    expect($match->fresh()->assigned_umpire_id)->toBe($umpire->id);
});

test('an umpire with a match in progress cannot switch until it is finished', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    roleSwitchMatch($umpire, 'in_progress');

    $this->actingAs($umpire);

    Volt::test('account.role-switch')
        ->call('switchRole')
        ->assertNoRedirect()
        ->assertDispatched('system-message');

    expect(session()->has(User::PLAYER_MODE_SESSION_KEY))->toBeFalse();
});

test('an umpire can always switch back, even with a match in progress', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    roleSwitchMatch($umpire, 'in_progress');

    $this->actingAs($umpire)->withSession([User::PLAYER_MODE_SESSION_KEY => true]);

    Volt::test('account.role-switch')
        ->call('switchRole')
        ->assertRedirect(route('umpire.dashboard'));
});

test('admins and players cannot use the role switch', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    Volt::test('account.role-switch')->call('switchRole')->assertForbidden();
})->with(['admin', 'player']);

test('the player side is only ever on for the signed-in umpire themselves', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->withSession([User::PLAYER_MODE_SESSION_KEY => true]);

    expect($umpire->isInPlayerMode())->toBeFalse()
        ->and($umpire->isUmpire())->toBeTrue()
        ->and($admin->isInPlayerMode())->toBeFalse()
        ->and($admin->isAdmin())->toBeTrue();
});

test('the player side ends when the umpire signs out and back in', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);

    $this->actingAs($umpire)->withSession([User::PLAYER_MODE_SESSION_KEY => true]);
    $this->post(route('logout'));

    expect(session()->has(User::PLAYER_MODE_SESSION_KEY))->toBeFalse();

    $this->actingAs($umpire)->get(route('umpire.dashboard'))->assertOk();
});

test('switching to the player side asks for confirmation first and explains what changes', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);

    $this->actingAs($umpire);

    Volt::test('account.role-switch')
        ->assertSee('Switch to the player side?')
        ->assertSee('You will not be able to score matches while you are there.')
        ->assertSee('Your umpire account and assigned matches stay as they are')
        ->assertSee('Yes, switch to Player')
        ->assertSee('Cancel')
        ->assertDontSee('Yes, switch to Umpire');

    // Opening the sidebar button alone changes nothing.
    expect(session()->has(User::PLAYER_MODE_SESSION_KEY))->toBeFalse()
        ->and($umpire->fresh()->role)->toBe('umpire');
});

test('switching back asks for confirmation too', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);

    $this->actingAs($umpire)->withSession([User::PLAYER_MODE_SESSION_KEY => true]);

    Volt::test('account.role-switch')
        ->assertSee('Switch back to the umpire side?')
        ->assertSee('Yes, switch to Umpire')
        ->assertDontSee('Yes, switch to Player');
});

test('the sidebar button opens the confirmation instead of switching straight away', function () {
    $this->actingAs(User::factory()->create(['role' => 'umpire']))
        ->get(route('umpire.dashboard'))
        ->assertOk()
        ->assertSee('confirm-role-switch', false)
        ->assertSee('Switch to the player side?');
});

/**
 * An open tournament with one singles category an umpire (a man, here) can enter.
 */
function roleSwitchOpenCategory(string $name = 'Open Cup'): TournamentCategory
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

/**
 * Register the signed-in user (acting as a player) for a category.
 */
function roleSwitchRegister(TournamentCategory $tournamentCategory): void
{
    Volt::test('player.events')
        ->call('viewTournament', $tournamentCategory->tournament_id)
        ->call('startRegistration')
        ->set('regFirstName', 'Gwen')
        ->set('regLastName', 'Domine')
        ->set('regBirthDate', now()->subYears(25)->toDateString())
        ->set('regGender', 'male')
        ->set('regAddress', 'Purok 5, Ozamiz City')
        ->set('regSkillLevel', 'Beginner')
        ->set('regPhoneNumber', '09171234567')
        ->set('regTournamentCategoryId', $tournamentCategory->id)
        ->call('registrationNextStep')
        ->assertHasNoErrors()
        ->set('ownDocumentType', 'national_id')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('submitRegistration')
        ->assertHasNoErrors();
}

test('an umpire who switches to a player, registers for a tournament and switches back keeps everything', function () {
    Storage::fake('public');

    $umpire = User::factory()->create(['role' => 'umpire']);
    $assigned = roleSwitchMatch($umpire, 'scheduled');
    $category = roleSwitchOpenCategory();

    $this->actingAs($umpire);

    // 1. Switch to the player side.
    Volt::test('account.role-switch')->call('switchRole')->assertRedirect(route('player.dashboard'));

    // 2. Register for a tournament as a player.
    roleSwitchRegister($category);

    $application = PlayerApplication::where('tournament_category_id', $category->id)->sole();
    $player = $umpire->fresh()->player;

    expect($application->status)->toBe('pending_verification')
        ->and($application->applicationPlayers->pluck('player_id')->all())->toBe([$player->id])
        ->and($player->user_id)->toBe($umpire->id)
        ->and($player->name)->toBe('Gwen Domine');

    // 3. Switch back to the umpire side.
    Volt::test('account.role-switch')->call('switchRole')->assertRedirect(route('umpire.dashboard'));

    // Still an umpire, with the match still theirs and the registration still on record.
    expect($umpire->fresh()->role)->toBe('umpire')
        ->and($assigned->fresh()->assigned_umpire_id)->toBe($umpire->id)
        ->and(PlayerApplication::where('tournament_category_id', $category->id)->count())->toBe(1);

    $this->get(route('umpire.dashboard'))->assertOk()->assertSee('Court 1');
    $this->get(route('umpire.matches'))->assertOk();
    // Player pages are closed again while they are on the umpire side.
    $this->get(route('player.events'))->assertRedirect(route('umpire.dashboard'));

    // 4. Switching to the player side again shows the registration they made.
    Volt::test('account.role-switch')->call('switchRole');

    Volt::test('player.events')->set('tab', 'applications')->assertSee('Open Cup');
});

test('the registration is not scored, scheduled or assigned as umpire work', function () {
    Storage::fake('public');

    $umpire = User::factory()->create(['role' => 'umpire']);
    $category = roleSwitchOpenCategory();

    $this->actingAs($umpire);

    Volt::test('account.role-switch')->call('switchRole');
    roleSwitchRegister($category);
    Volt::test('account.role-switch')->call('switchRole');

    expect(Matches::where('assigned_umpire_id', $umpire->id)->count())->toBe(0)
        ->and(Matches::where('tournament_category_id', $category->id)->count())->toBe(0);

    Volt::test('umpire.dashboard')
        ->assertSet('assignedMatchesCount', 0)
        ->assertSet('accomplishedMatchesCount', 0);
});

test('an umpire who switches to a player still cannot enter a tournament they are assigned to officiate', function () {
    Storage::fake('public');

    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = roleSwitchMatch($umpire, 'scheduled');
    $category = $match->tournamentCategory;
    $category->update(['status' => 'open']);
    $category->tournament->update([
        'date_start' => now()->addDays(20)->toDateString(),
        'date_end' => now()->addDays(21)->toDateString(),
        'registration_start' => now()->subDay()->toDateString(),
        'registration_end' => now()->addDays(10)->toDateString(),
    ]);

    $this->actingAs($umpire);

    Volt::test('account.role-switch')->call('switchRole');

    Volt::test('player.events')
        ->call('viewTournament', $category->tournament_id)
        ->call('startRegistration')
        ->assertDispatched('system-message', title: 'Cannot register');

    expect(PlayerApplication::where('tournament_category_id', $category->id)->count())->toBe(0)
        ->and($match->fresh()->assigned_umpire_id)->toBe($umpire->id);
});
