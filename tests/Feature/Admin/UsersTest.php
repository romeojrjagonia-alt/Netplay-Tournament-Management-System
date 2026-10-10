<?php

use App\Models\User;
use Livewire\Volt\Volt;

it('lets an admin create an umpire account', function () {
    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.users.index')
        ->set('name', 'Pedro Reyes')
        ->set('email', 'pedro@example.test')
        ->set('password', 'secret-pass-1')
        ->set('password_confirmation', 'secret-pass-1')
        ->call('createUmpire')
        ->assertHasNoErrors()
        ->assertSee('pedro@example.test');

    $umpire = User::where('email', 'pedro@example.test')->first();

    expect($umpire->role)->toBe('umpire')
        ->and($umpire->email_verified_at)->not->toBeNull();
});

it('validates new umpire details', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.users.index')
        ->set('email', 'taken@example.test')
        ->set('password', 'short')
        ->call('createUmpire')
        ->assertHasErrors(['name', 'email', 'password']);
});

it('keeps the users page away from non-admins', function (string $role) {
    Volt::actingAs(User::factory()->create(['role' => $role]))
        ->test('admin.users.index')
        ->assertForbidden();
})->with(['player', 'umpire']);

it('no longer lets visitors register themselves as umpires', function () {
    Volt::test('auth.register')
        ->call('selectUserType', 'umpire')
        ->call('nextStep')
        ->assertHasErrors(['userType'])
        ->assertSet('step', 1);
});

it('lists ten accounts per page and resets to page one when the role filter changes', function () {
    User::factory()->count(14)->create(['role' => 'player']);

    $component = Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('admin.users.index')
        ->assertSee('Accounts (15)');

    expect($component->get('users')->items())->toHaveCount(10)
        ->and($component->get('users')->lastPage())->toBe(2);

    $component->call('gotoPage', 2)->set('roleFilter', 'player');

    expect($component->get('users')->currentPage())->toBe(1)
        ->and($component->get('users')->total())->toBe(14)
        ->and($component->get('users')->items())->toHaveCount(10);
});
