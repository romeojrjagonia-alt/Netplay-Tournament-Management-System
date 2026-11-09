<?php

use App\Mail\PasswordChanged;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('admin and umpire pages offer a profile modal', function (string $role, string $route) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route($route))
        ->assertOk()
        ->assertSee('My Profile');
})->with([
    'admin' => ['admin', 'admin.dashboard'],
    'umpire' => ['umpire', 'umpire.dashboard'],
]);

test('staff can change their name and upload a picture', function (string $role) {
    Storage::fake('public');
    $user = User::factory()->create(['role' => $role, 'name' => 'Old Name']);

    Volt::actingAs($user)->test('staff.profile-modal')
        ->call('startEditing')
        ->set('name', 'New Name')
        ->set('photo', UploadedFile::fake()->create('me.jpg', 10, 'image/jpeg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    $user->refresh();

    expect($user->name)->toBe('New Name')
        ->and($user->photo_path)->not->toBeNull()
        ->and($user->profilePhotoUrl())->not->toBeNull();

    Storage::disk('public')->assertExists($user->photo_path);
})->with(['admin', 'umpire']);

test('replacing or removing the picture deletes the old file', function () {
    Storage::fake('public');
    $user = User::factory()->create(['role' => 'admin']);

    $component = Volt::actingAs($user)->test('staff.profile-modal')
        ->call('startEditing')
        ->set('photo', UploadedFile::fake()->create('one.jpg', 10, 'image/jpeg'))
        ->call('save');

    $first = $user->refresh()->photo_path;

    $component->call('startEditing')
        ->set('photo', UploadedFile::fake()->create('two.jpg', 10, 'image/jpeg'))
        ->call('save');

    Storage::disk('public')->assertMissing($first);
    $second = $user->refresh()->photo_path;

    $component->call('removePhoto');

    Storage::disk('public')->assertMissing($second);
    expect($user->refresh()->photo_path)->toBeNull();
});

test('the picture must be an image', function () {
    Storage::fake('public');

    Volt::actingAs(User::factory()->create(['role' => 'umpire']))->test('staff.profile-modal')
        ->call('startEditing')
        ->set('photo', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('photo');
});

test('staff can change their password through the three steps', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => 'admin', 'password' => 'OldPassword1']);

    Volt::actingAs($user)->test('staff.profile-modal')
        ->call('openSecurity')
        ->set('currentPassword', 'OldPassword1')
        ->call('verifyCurrentPassword')
        ->assertSet('passwordStep', 2)
        ->set('newPassword', 'NewPassword2')
        ->call('submitNewPassword')
        ->assertSet('passwordStep', 3)
        ->set('newPasswordConfirmation', 'NewPassword2')
        ->call('confirmNewPassword')
        ->assertHasNoErrors()
        ->assertSet('securityView', false);

    expect(Hash::check('NewPassword2', $user->refresh()->password))->toBeTrue();
    Mail::assertQueued(PasswordChanged::class);
});

test('a wrong current password does not advance', function () {
    $user = User::factory()->create(['role' => 'umpire', 'password' => 'OldPassword1']);

    Volt::actingAs($user)->test('staff.profile-modal')
        ->call('openSecurity')
        ->set('currentPassword', 'WrongPassword1')
        ->call('verifyCurrentPassword')
        ->assertHasErrors('currentPassword')
        ->assertSet('passwordStep', 1);
});
