<?php

use App\Models\User;
use Livewire\Volt\Volt;

function actingAsAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

it('lets an admin add a verified account with the chosen role', function (string $role) {
    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openCreateModal')
        ->set('name', 'Pedro Reyes')
        ->set('email', 'pedro@example.test')
        ->set('role', $role)
        ->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'secret-pass-1')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('pedro@example.test');

    $user = User::where('email', 'pedro@example.test')->first();

    expect($user->role)->toBe($role)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-pass-1', $user->password))->toBeTrue();
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
        ->assertHasErrors(['name', 'email', 'role', 'password']);
});

it('lets an admin edit a user without changing the password', function () {
    $user = User::factory()->create(['role' => 'player', 'email' => 'old@example.test']);
    $oldPassword = $user->password;

    Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->call('openEditModal', $user->id)
        ->assertSet('name', $user->name)
        ->assertSet('email', 'old@example.test')
        ->set('name', 'Renamed User')
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
        ->set('password', 'brand-new-pass')
        ->set('password_confirmation', 'brand-new-pass')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
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

    expect($component->get('users')->total())->toBe(1);

    $component->set('search', 'else@example');

    expect($component->get('users')->total())->toBe(1);
});

it('keeps the users page away from non-admins', function (string $role) {
    Volt::actingAs(User::factory()->create(['role' => $role]))
        ->test('admin.users.index')
        ->assertForbidden();
})->with(['player', 'umpire']);

it('lists ten accounts per page and resets to page one when the role filter changes', function () {
    User::factory()->count(14)->create(['role' => 'player']);

    $component = Volt::actingAs(actingAsAdmin())
        ->test('admin.users.index')
        ->assertSee('All Users');

    expect($component->get('users')->items())->toHaveCount(10)
        ->and($component->get('users')->total())->toBe(15)
        ->and($component->get('users')->lastPage())->toBe(2);

    $component->call('gotoPage', 2)->set('roleFilter', 'player');

    expect($component->get('users')->currentPage())->toBe(1)
        ->and($component->get('users')->total())->toBe(14)
        ->and($component->get('users')->items())->toHaveCount(10);
});
