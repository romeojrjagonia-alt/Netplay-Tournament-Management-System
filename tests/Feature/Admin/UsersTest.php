<?php

use App\Models\Player;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

function actingAsAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

/**
 * The sign-up profile an admin fills in when adding an account.
 */
function fillSignupProfile($component, array $overrides = [])
{
    $fields = array_merge([
        'firstName' => 'Pedro',
        'lastName' => 'Reyes',
        'middleInitial' => 'D',
        'birthDate' => '1999-05-05',
        'gender' => 'male',
        'phoneNumber' => '09171234567',
        'purok' => 'Purok 5',
        'city' => 'Ozamiz City',
        'province' => 'Misamis Occidental',
        'club' => 'OBC Badminton Club',
    ], $overrides);

    foreach ($fields as $property => $value) {
        $component->set($property, $value);
    }

    return $component;
}

it('lets an admin add a verified account with the chosen role and the sign-up profile', function (string $role) {
    Notification::fake();

    $component = Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openCreateModal');

    fillSignupProfile($component)
        ->set('email', 'pedro@example.test')
        ->set('role', $role)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('pedro@example.test');

    $user = User::where('email', 'pedro@example.test')->first();

    expect($user->name)->toBe('Pedro D. Reyes')
        ->and($user->role)->toBe($role)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->player->name)->toBe('Pedro D. Reyes')
        ->and($user->player->email)->toBe('pedro@example.test')
        ->and($user->player->birth_date->toDateString())->toBe('1999-05-05')
        ->and($user->player->gender)->toBe('male')
        ->and($user->player->phone_number)->toBe('09171234567')
        ->and($user->player->address)->toBe('Purok 5, Ozamiz City, Misamis Occidental')
        ->and($user->player->club)->toBe('OBC Badminton Club');
})->with(['admin', 'umpire', 'player']);

it('validates new account details', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openCreateModal')
        ->set('email', 'taken@example.test')
        ->set('role', 'superuser')
        ->call('save')
        ->assertHasErrors(['firstName', 'lastName', 'email', 'role', 'birthDate', 'gender', 'phoneNumber', 'purok', 'city', 'province']);
});

it('emails a new account a link to set its own password instead of letting the admin pick one', function () {
    Notification::fake();

    $component = Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openCreateModal');

    fillSignupProfile($component)
        ->set('email', 'invited@example.test')
        ->set('role', 'player')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'invited@example.test')->first();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('has no way for an admin to edit a user\'s details or password', function () {
    $component = Volt::actingAs(actingAsAdmin())->test('admin.users.index');

    expect(method_exists($component->instance(), 'openEditModal'))->toBeFalse()
        ->and(property_exists($component->instance(), 'password'))->toBeFalse();

    $component->assertDontSee('Edit')->assertDontSee('New password');
});

it('lets an admin change only the role of another user', function () {
    $user = User::factory()->create(['role' => 'player', 'name' => 'Same Name', 'email' => 'same@example.test']);
    $password = $user->password;

    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('setRole', $user->id, 'umpire');

    $user->refresh();

    expect($user->role)->toBe('umpire')
        ->and($user->name)->toBe('Same Name')
        ->and($user->email)->toBe('same@example.test')
        ->and($user->password)->toBe($password);
});

it('rejects an unknown role', function () {
    $user = User::factory()->create(['role' => 'player']);

    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('setRole', $user->id, 'superuser')
        ->assertStatus(422);

    expect($user->fresh()->role)->toBe('player');
});

it('stops an admin from changing their own role', function () {
    $admin = actingAsAdmin();

    Volt::actingAs($admin)
        ->test('admin.users.index')
        ->call('setRole', $admin->id, 'player');

    expect($admin->fresh()->role)->toBe('admin');
});

it('deletes a user', function () {
    $user = User::factory()->create(['role' => 'umpire']);

    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('delete', $user->id);

    expect(User::find($user->id))->toBeNull();
});

it('stops an admin from deleting their own account', function () {
    $admin = actingAsAdmin();

    Volt::actingAs($admin)
        ->test('admin.users.index')
        ->call('delete', $admin->id);

    expect(User::find($admin->id))->not->toBeNull();
});

it('searches users by name or email', function () {
    User::factory()->create(['name' => 'Zelda Findme', 'email' => 'zelda@example.test']);
    User::factory()->create(['name' => 'Someone Else', 'email' => 'else@example.test']);

    $component = Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->set('search', 'findme');

    expect($component->get('users'))->toHaveCount(1);

    $component->set('search', 'else@example');

    expect($component->get('users'))->toHaveCount(1);
});

it('keeps the users page away from non-admins', function (string $role) {
    Volt::actingAs(User::factory()->create(['role' => $role]))
        ->test('admin.users.index')
        ->assertForbidden();
})->with(['player', 'umpire']);

it('lists accounts in pages and narrows them by role', function () {
    User::factory()->count(14)->create(['role' => 'player']);

    $component = Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->assertSee('All Users');

    expect($component->get('users'))->toHaveCount(10)
        ->and($component->get('users')->total())->toBe(15);

    $component->set('roleFilter', 'player');

    expect($component->get('users')->total())->toBe(14);
});

test('a player account created by an admin gets a player record and can open the player pages', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Volt::test('admin.users.index')
        ->call('openCreateModal')
        ->set('role', 'player')
        ->set('firstName', 'Admin')
        ->set('lastName', 'Made')
        ->set('birthDate', '2001-02-03')
        ->set('gender', 'female')
        ->set('phoneNumber', '09170000000')
        ->set('purok', 'Purok 1')
        ->set('city', 'Ozamiz City')
        ->set('province', 'Misamis Occidental')
        ->set('email', 'adminmade@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'adminmade@example.com')->first();

    expect($user->player)->not->toBeNull()
        ->and($user->player->name)->toBe('Admin Made')
        ->and($user->player->email)->toBe('adminmade@example.com');

    auth()->logout();
    $this->actingAs($user);

    $this->get(route('player.dashboard'))->assertOk();
    $this->get(route('player.events'))->assertOk();
    $this->get(route('player.matches'))->assertOk();
});

test('a player account that has no player record gets one the first time it is used', function () {
    $user = User::factory()->create(['role' => 'player', 'name' => 'Orphan Account']);

    expect($user->player)->toBeNull();

    $this->actingAs($user)->get(route('player.dashboard'))->assertOk();

    expect($user->fresh()->player?->name)->toBe('Orphan Account');
});

test('switching an account to the player role gets its player record the first time it is used', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $umpire = User::factory()->create(['role' => 'umpire']);

    Volt::test('admin.users.index')->call('setRole', $umpire->id, 'player');

    $this->actingAs($umpire->fresh())->get(route('player.dashboard'))->assertOk();

    expect($umpire->fresh()->player)->not->toBeNull();
});

test('the users list shows each user\'s uploaded profile picture', function () {
    $admin = actingAsAdmin();
    User::factory()->create(['role' => 'umpire', 'name' => 'Umpire With Photo', 'photo_path' => 'staff-photos/umpire.jpg']);

    $player = User::factory()->create(['role' => 'player', 'name' => 'Player With Photo']);
    Player::create(['user_id' => $player->id, 'name' => 'Player With Photo', 'photo_path' => 'player-photos/player.jpg']);

    $html = Volt::actingAs($admin)->test('admin.users.index')->html();

    expect($html)
        ->toContain('storage/staff-photos/umpire.jpg')
        ->toContain('storage/player-photos/player.jpg');
});

test('users without a picture keep their initials badge', function () {
    $admin = actingAsAdmin();
    User::factory()->create(['role' => 'umpire', 'name' => 'Gwen Domine', 'photo_path' => null]);

    Volt::actingAs($admin)->test('admin.users.index')
        ->assertSee('GD')
        ->assertDontSeeHtml('alt="Gwen Domine"');
});

test('loading the users list does not query a player record per user', function () {
    $admin = actingAsAdmin();

    foreach (range(1, 8) as $number) {
        $user = User::factory()->create(['role' => 'player']);
        Player::create(['user_id' => $user->id, 'name' => "Player $number"]);
    }

    DB::enableQueryLog();
    Volt::actingAs($admin)->test('admin.users.index')->html();
    $playerQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "players"') || str_contains($query['query'], 'from `players`'))->count();
    DB::disableQueryLog();

    expect($playerQueries)->toBeLessThan(5);
});
