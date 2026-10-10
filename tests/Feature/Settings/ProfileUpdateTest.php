<?php

use App\Models\Player;
use App\Models\User;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
test('changing the account email also updates the linked player contact email', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name, 'email' => 'old-contact@example.com']);

    $this->actingAs($user);

    Volt::test('settings.profile')
        ->set('name', $user->name)
        ->set('email', 'new-address@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($player->fresh()->email)->toBe('new-address@example.com');
});

test('saving the profile without an email change leaves the player contact email alone', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name, 'email' => 'contact@example.com']);

    $this->actingAs($user);

    Volt::test('settings.profile')
        ->set('name', 'Renamed Player')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    expect($player->fresh()->email)->toBe('contact@example.com');
});
