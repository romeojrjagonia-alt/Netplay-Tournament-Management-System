<?php

use App\Models\Player;
use App\Models\PlayerStatistic;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

test('player dashboard shows a profile trigger and the player\'s details in the modal', function () {
    $user = User::factory()->create(['role' => 'player']);

    $player = Player::create([
        'user_id' => $user->id,
        'name' => 'Juan Dela Cruz',
        'birth_date' => '2002-04-22',
        'gender' => 'male',
        'phone_number' => '09301234567',
        'address' => 'P-2 Molicay, Ozamiz City, Misamis Occidental',
        'club' => 'OBC',
        'level' => 'Beginner',
    ]);

    PlayerStatistic::create([
        'player_id' => $player->id,
        'matches_played' => 10,
        'wins' => 7,
        'losses' => 3,
    ]);

    $response = $this->actingAs($user)->get(route('player.dashboard'));

    $response->assertOk();
    $response->assertSee('data-flux-modal-trigger', false);
    $response->assertSee('Juan Dela Cruz');
    $response->assertSee('Beginner');
    $response->assertSee('70% Win Rate');
    $response->assertSee('09301234567');
    $response->assertSee('P-2 Molicay, Ozamiz City, Misamis Occidental');
});

test('admin dashboard keeps a non-interactive profile pill', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertDontSee('data-flux-modal-trigger', false);
});

test('player can edit their name, address, email, and phone number from the profile modal', function () {
    $user = User::factory()->create(['role' => 'player', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com']);

    Player::create([
        'user_id' => $user->id,
        'name' => 'Juan Dela Cruz',
        'phone_number' => '09301234567',
        'address' => 'Old Address',
    ]);

    $this->actingAs($user);

    Volt::test('player.profile-modal')
        ->call('startEditing')
        ->assertSet('name', 'Juan Dela Cruz')
        ->assertSet('email', 'juan@example.com')
        ->assertSet('address', 'Old Address')
        ->assertSet('phoneNumber', '09301234567')
        ->set('name', 'Juan D. Cruz')
        ->set('email', 'juan.cruz@example.com')
        ->set('address', 'New Address')
        ->set('phoneNumber', '09991234567')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    $user->refresh();
    $player = $user->player()->first();

    expect($user->name)->toBe('Juan D. Cruz');
    expect($user->email)->toBe('juan.cruz@example.com');
    expect($player->name)->toBe('Juan D. Cruz');
    expect($player->address)->toBe('New Address');
    expect($player->phone_number)->toBe('09991234567');
});

test('unverified players see a verify account button in the profile modal', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => 'Juan Dela Cruz', 'address' => 'Ozamiz City']);

    $this->actingAs($user);

    Volt::test('player.profile-modal')
        ->assertSee('Verify account')
        ->call('sendVerification')
        ->assertSet('verificationStatus', 'sent')
        ->assertSee('Verification link sent');

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('verified players do not see the verify account button', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => 'Juan Dela Cruz', 'address' => 'Ozamiz City']);

    $this->actingAs($user);

    Volt::test('player.profile-modal')
        ->assertDontSee('Verify account');
});

test('changing email from the profile modal makes the player unverified again', function () {
    $user = User::factory()->create(['role' => 'player', 'email' => 'old@example.com']);
    Player::create(['user_id' => $user->id, 'name' => 'Juan Dela Cruz', 'address' => 'Ozamiz City']);

    $this->actingAs($user);

    Volt::test('player.profile-modal')
        ->call('startEditing')
        ->set('email', 'new@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Verify account');

    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('editing the profile rejects an email already taken by another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $user = User::factory()->create(['role' => 'player', 'name' => 'Juan Dela Cruz', 'email' => 'juan@example.com']);

    Player::create([
        'user_id' => $user->id,
        'name' => 'Juan Dela Cruz',
        'address' => 'Old Address',
    ]);

    $this->actingAs($user);

    Volt::test('player.profile-modal')
        ->call('startEditing')
        ->set('email', 'taken@example.com')
        ->call('save')
        ->assertHasErrors(['email' => 'unique'])
        ->assertSet('editing', true);

    expect($user->refresh()->email)->toBe('juan@example.com');
});
