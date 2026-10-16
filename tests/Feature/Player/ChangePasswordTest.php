<?php

use App\Mail\PasswordChanged;
use App\Models\Player;
use App\Models\User;
use App\Rules\AccountPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Volt\Volt;

function passwordPlayer(?string $password = 'OldPassword1'): User
{
    $user = User::factory()->create([
        'role' => 'player',
        'email' => 'pat@example.com',
        'password' => $password,
    ]);

    Player::create(['user_id' => $user->id, 'name' => 'Pat Player', 'email' => $user->email]);

    return $user;
}

test('the account password rule accepts only 8+ letters and digits with both cases and a number', function (string $password, bool $valid) {
    $passes = Validator::make(['password' => $password], ['password' => [new AccountPassword]])->passes();

    expect($passes)->toBe($valid);
})->with([
    'valid' => ['Badminton2026', true],
    'too short' => ['Abc12', false],
    'no uppercase' => ['badminton2026', false],
    'no lowercase' => ['BADMINTON2026', false],
    'no number' => ['BadmintonClub', false],
    'has a space' => ['Bad minton2026', false],
    'has a special character' => ['Badminton2026!', false],
]);

test('changing the password goes through the three steps, shows success and emails the player', function () {
    Mail::fake();

    $user = passwordPlayer();
    $this->actingAs($user);

    Volt::test('player.profile-modal')
        ->call('openSecurity')
        ->assertSet('passwordStep', 1)
        ->set('currentPassword', 'OldPassword1')
        ->call('verifyCurrentPassword')
        ->assertSet('passwordStep', 2)
        ->set('newPassword', 'NewPassword2')
        ->call('submitNewPassword')
        ->assertSet('passwordStep', 3)
        ->set('newPasswordConfirmation', 'NewPassword2')
        ->call('confirmNewPassword')
        ->assertHasNoErrors()
        ->assertSet('securityView', false)
        ->assertDispatched('system-message', variant: 'success', title: 'Password changed');

    expect(Hash::check('NewPassword2', $user->fresh()->password))->toBeTrue();

    Mail::assertQueued(PasswordChanged::class, fn ($mail) => $mail->hasTo('pat@example.com'));
});

test('a wrong current password does not advance and is rate limited', function () {
    $user = passwordPlayer();
    $this->actingAs($user);
    RateLimiter::clear('player-password:'.$user->id);

    $component = Volt::test('player.profile-modal')->call('openSecurity');

    foreach (range(1, 5) as $attempt) {
        $component->set('currentPassword', 'WrongPassword1')
            ->call('verifyCurrentPassword')
            ->assertHasErrors('currentPassword')
            ->assertSet('passwordStep', 1);
    }

    $component->set('currentPassword', 'OldPassword1')
        ->call('verifyCurrentPassword')
        ->assertHasErrors('currentPassword')
        ->assertSet('passwordStep', 1);

    expect(Hash::check('OldPassword1', $user->fresh()->password))->toBeTrue();
});

test('the new password must follow the rules and differ from the current one', function () {
    $this->actingAs(passwordPlayer());

    $component = Volt::test('player.profile-modal')
        ->call('openSecurity')
        ->set('currentPassword', 'OldPassword1')
        ->call('verifyCurrentPassword');

    foreach (['short1A', 'alllowercase1', 'NoNumberHere', 'Has Space1A', 'Special!Char1', 'OldPassword1'] as $bad) {
        $component->set('newPassword', $bad)
            ->call('submitNewPassword')
            ->assertHasErrors('newPassword')
            ->assertSet('passwordStep', 2);
    }
});

test('the confirmation must match, and nothing changes or is emailed until it does', function () {
    Mail::fake();

    $user = passwordPlayer();
    $this->actingAs($user);

    Volt::test('player.profile-modal')
        ->call('openSecurity')
        ->set('currentPassword', 'OldPassword1')
        ->call('verifyCurrentPassword')
        ->set('newPassword', 'NewPassword2')
        ->call('submitNewPassword')
        ->set('newPasswordConfirmation', 'NewPassword9')
        ->call('confirmNewPassword')
        ->assertHasErrors('newPasswordConfirmation')
        ->assertSet('passwordStep', 3);

    expect(Hash::check('OldPassword1', $user->fresh()->password))->toBeTrue();
    Mail::assertNothingQueued();
});

test('the steps cannot be skipped without verifying the current password', function () {
    $user = passwordPlayer();
    $this->actingAs($user);

    Volt::test('player.profile-modal')
        ->call('openSecurity')
        ->set('newPassword', 'NewPassword2')
        ->call('submitNewPassword')
        ->assertForbidden();

    Volt::test('player.profile-modal')
        ->call('openSecurity')
        ->set('newPassword', 'NewPassword2')
        ->set('newPasswordConfirmation', 'NewPassword2')
        ->call('confirmNewPassword')
        ->assertForbidden();

    expect(Hash::check('OldPassword1', $user->fresh()->password))->toBeTrue();
});

test('a Google account with no password sets its first one without a current password', function () {
    Mail::fake();

    $user = passwordPlayer();
    $user->forceFill(['password' => null, 'google_id' => 'g-123'])->save();
    $this->actingAs($user);

    Volt::test('player.profile-modal')
        ->call('openSecurity')
        ->assertSet('passwordStep', 2)
        ->assertSee('Step 1 of 2')
        ->set('newPassword', 'FirstPassword1')
        ->call('submitNewPassword')
        ->set('newPasswordConfirmation', 'FirstPassword1')
        ->call('confirmNewPassword')
        ->assertHasNoErrors();

    expect(Hash::check('FirstPassword1', $user->fresh()->password))->toBeTrue();
    Mail::assertQueued(PasswordChanged::class);
});

test('sign-up and the settings page use the same password rules', function () {
    $user = passwordPlayer();
    $this->actingAs($user);

    Volt::test('settings.password')
        ->set('current_password', 'OldPassword1')
        ->set('password', 'alllowercase1')
        ->set('password_confirmation', 'alllowercase1')
        ->call('updatePassword')
        ->assertHasErrors('password');

    Volt::test('settings.password')
        ->set('current_password', 'OldPassword1')
        ->set('password', 'NewPassword2')
        ->set('password_confirmation', 'NewPassword2')
        ->call('updatePassword')
        ->assertHasNoErrors();
});
