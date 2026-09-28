<?php

use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('registration wizard starts on step one', function () {
    Volt::test('auth.register')
        ->assertSet('step', 1);
});

test('step one requires a user type before advancing', function () {
    Volt::test('auth.register')
        ->call('nextStep')
        ->assertHasErrors(['userType' => 'required'])
        ->assertSet('step', 1);
});

test('selecting a user type advances to step two', function () {
    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->assertSet('userType', 'player')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertDontSee('Sign Up with Google');
});

test('step two requires valid account details before advancing', function () {
    Volt::test('auth.register')
        ->call('selectUserType', 'umpire')
        ->call('nextStep')
        ->set('password', 'short')
        ->set('password_confirmation', 'mismatch')
        ->call('nextStep')
        ->assertHasErrors(['firstName', 'lastName', 'email', 'password'])
        ->assertSet('step', 2);
});

test('completing step two advances to the final profile step', function () {
    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->call('nextStep')
        ->set('firstName', 'Juan')
        ->set('lastName', 'Dela Cruz')
        ->set('email', 'juan@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 3);
});

test('previous step navigates backward without validating', function () {
    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->call('nextStep')
        ->call('previousStep')
        ->assertSet('step', 1);
});

test('final step requires valid profile details before registering', function () {
    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->call('nextStep')
        ->set('firstName', 'Juan')
        ->set('lastName', 'Dela Cruz')
        ->set('email', 'juan@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('nextStep')
        ->call('register')
        ->assertHasErrors(['birthDate', 'gender', 'purok', 'city', 'province'])
        ->assertSet('step', 3);

    $this->assertGuest();
});

test('completing the wizard creates a user and player and logs them in', function () {
    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->call('nextStep')
        ->set('firstName', 'Juan')
        ->set('lastName', 'Dela Cruz')
        ->set('middleInitial', 'D')
        ->set('email', 'juan@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('nextStep')
        ->set('birthDate', '2000-02-04')
        ->set('gender', 'male')
        ->set('purok', 'Purok 5')
        ->set('city', 'Ozamiz City')
        ->set('province', 'Misamis Occidental')
        ->set('club', 'OBC Badminton Club')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('player.dashboard', absolute: false));

    $user = User::where('email', 'juan@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Juan D. Dela Cruz');
    expect($user->role)->toBe('player');
    expect($user->email_verified_at)->not->toBeNull();

    $player = Player::where('user_id', $user->id)->first();

    expect($player)->not->toBeNull();
    expect($player->address)->toBe('Purok 5, Ozamiz City, Misamis Occidental');
    expect($player->club)->toBe('OBC Badminton Club');
    expect($player->age)->toBeGreaterThan(20);

    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);
});

test('registration rejects an email that is already taken', function () {
    User::factory()->create(['email' => 'juan@example.com']);

    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->call('nextStep')
        ->set('firstName', 'Juan')
        ->set('lastName', 'Dela Cruz')
        ->set('email', 'juan@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('nextStep')
        ->assertHasErrors(['email' => 'unique']);
});
