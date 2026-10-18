<?php

use App\Models\Player;
use App\Models\User;
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
    $component = Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openCreateModal');

    fillSignupProfile($component)
        ->set('email', 'pedro@example.test')
        ->set('role', $role)
        ->set('password', 'SecretPass1')
        ->set('password_confirmation', 'SecretPass1')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('pedro@example.test');

    $user = User::where('email', 'pedro@example.test')->first();

    expect($user->name)->toBe('Pedro D. Reyes')
        ->and($user->role)->toBe($role)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('SecretPass1', $user->password))->toBeTrue()
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
        ->set('password', 'short')
        ->call('save')
        ->assertHasErrors(['firstName', 'lastName', 'email', 'role', 'password', 'birthDate', 'gender', 'phoneNumber', 'purok', 'city', 'province']);
});

it('lets an admin edit a user without changing the password', function () {
    $user = User::factory()->create(['role' => 'player', 'email' => 'old@example.test']);
    $oldPassword = $user->password;

    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openEditModal', $user->id)
        ->assertSet('email', 'old@example.test')
        ->set('firstName', 'Renamed')
        ->set('lastName', 'User')
        ->set('middleInitial', '')
        ->set('email', 'new@example.test')
        ->set('role', 'umpire')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Renamed User')
        ->and($user->email)->toBe('new@example.test')
        ->and($user->role)->toBe('umpire')
        ->and($user->password)->toBe($oldPassword);
});

it('lets an admin set a new password while editing', function () {
    $user = User::factory()->create(['role' => 'player']);

    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openEditModal', $user->id)
        ->set('password', 'BrandNew123')
        ->set('password_confirmation', 'BrandNew123')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('BrandNew123', $user->fresh()->password))->toBeTrue();
});

it('allows keeping the same email when editing', function () {
    $user = User::factory()->create(['role' => 'player']);

    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openEditModal', $user->id)
        ->call('save')
        ->assertHasNoErrors();
});

it('stops an admin from removing their own admin role', function () {
    $admin = actingAsAdmin();

    Volt::actingAs($admin)
        ->test('admin.users.index')
        ->call('openEditModal', $admin->id)
        ->set('role', 'player')
        ->call('save')
        ->assertHasErrors(['role']);

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

it('lists every account in one scrollable list and narrows it by role', function () {
    User::factory()->count(14)->create(['role' => 'player']);

    $component = Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->assertSee('All Users');

    expect($component->get('users'))->toHaveCount(15);

    $component->set('roleFilter', 'player');

    expect($component->get('users'))->toHaveCount(14);
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
        ->set('password', 'Password123')
        ->set('password_confirmation', 'Password123')
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

test('switching an account to the player role creates its player record', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $umpire = User::factory()->create(['role' => 'umpire']);

    Volt::test('admin.users.index')
        ->call('openEditModal', $umpire->id)
        ->set('role', 'player')
        ->call('save')
        ->assertHasNoErrors();

    expect($umpire->fresh()->player)->not->toBeNull();
});

it('prefills the sign-up profile when editing and updates the player record', function () {
    $user = User::factory()->create(['role' => 'player', 'name' => 'Ana M. Lopez']);
    Player::create([
        'user_id' => $user->id,
        'name' => 'Ana M. Lopez',
        'birth_date' => '2000-01-01',
        'gender' => 'female',
        'phone_number' => '09170001111',
        'address' => 'Purok 2, Ozamiz City, Misamis Occidental',
    ]);

    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openEditModal', $user->id)
        ->assertSet('firstName', 'Ana')
        ->assertSet('middleInitial', 'M')
        ->assertSet('lastName', 'Lopez')
        ->assertSet('purok', 'Purok 2')
        ->assertSet('city', 'Ozamiz City')
        ->assertSet('province', 'Misamis Occidental')
        ->assertSet('gender', 'female')
        ->set('phoneNumber', '09179998888')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->player->phone_number)->toBe('09179998888')
        ->and($user->fresh()->player->birth_date->toDateString())->toBe('2000-01-01');
});

it('requires passwords to follow the account password rules', function () {
    $component = Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openCreateModal');

    fillSignupProfile($component)
        ->set('email', 'rules@example.test')
        ->set('role', 'player')
        ->set('password', 'alllowercase1')
        ->set('password_confirmation', 'alllowercase1')
        ->call('save')
        ->assertHasErrors('password');
});
