<?php

use App\Mail\PasswordChanged;
use App\Models\Category;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentCategory;
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

/**
 * Give the umpire a match in the given status, in a new tournament.
 */
function umpireProfileMatch(User $umpire, string $status, string $tournamentName, ?string $scheduledAt = null): void
{
    $tournament = Tournament::create([
        'name' => $tournamentName,
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-10-20',
        'date_end' => '2026-10-22',
        'registration_start' => '2026-10-01',
        'registration_end' => '2026-10-18',
        'status' => 'published',
    ]);

    $category = Category::create(['name' => "Men's Singles ".$tournamentName, 'gender' => 'male', 'team_size' => 1]);

    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);

    Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => $status,
        'scheduled_at' => $scheduledAt ?? now()->addDay(),
        'court' => 'Court 4',
        'assigned_umpire_id' => $umpire->id,
    ]);
}

test('the profile details are not worked out until the modal is opened', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);

    Volt::actingAs($umpire)->test('staff.profile-modal')
        ->assertSet('detailsLoaded', false)
        ->assertSee('Loading details')
        ->assertSeeHtml('x-intersect.once="$wire.loadDetails()"')
        ->assertDontSee('Member since')
        ->call('loadDetails')
        ->assertSee('Member since')
        ->assertDontSee('Loading details');
});

test('every staff profile shows the member since date and email status', function (string $role) {
    $user = User::factory()->unverified()->create(['role' => $role, 'created_at' => '2026-03-05 10:00:00']);

    Volt::actingAs($user)->test('staff.profile-modal')
        ->call('loadDetails')
        ->assertSee('March 5, 2026')
        ->assertSee('Not verified');

    $user->markEmailAsVerified();

    Volt::actingAs($user->refresh())->test('staff.profile-modal')
        ->call('loadDetails')
        ->assertSee('Verified')
        ->assertDontSee('Not verified');
})->with(['admin', 'umpire']);

test('an umpire profile shows their officiating activity', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);

    umpireProfileMatch($umpire, 'completed', 'Past Cup One', now()->subDays(3)->toDateTimeString());
    umpireProfileMatch($umpire, 'completed', 'Past Cup Two', now()->subDays(2)->toDateTimeString());
    umpireProfileMatch($umpire, 'scheduled', 'Next Cup', now()->addDays(2)->setTime(9, 0)->toDateTimeString());
    umpireProfileMatch($umpire, 'in_progress', 'Live Cup', now()->addDays(5)->toDateTimeString());

    $other = User::factory()->create(['role' => 'umpire']);
    umpireProfileMatch($other, 'completed', 'Someone Elses Cup');

    $details = collect(Volt::actingAs($umpire)->test('staff.profile-modal')
        ->call('loadDetails')
        ->instance()->details)->pluck('value', 'label');

    expect($details['Upcoming matches'])->toBe('2')
        ->and($details['Matches officiated'])->toBe('2')
        ->and($details['Tournaments officiated'])->toBe('2')
        ->and($details['Next match'])->toContain('9:00 AM');

    Volt::actingAs($umpire)->test('staff.profile-modal')
        ->call('loadDetails')
        ->assertSee('Next Cup')
        ->assertSee('Court 4');
});

test('an umpire with no matches sees zeros and no upcoming match', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);

    Volt::actingAs($umpire)->test('staff.profile-modal')
        ->call('loadDetails')
        ->assertSee('Matches officiated')
        ->assertSee('No upcoming match');
});

test('an admin profile does not show officiating details', function () {
    Volt::actingAs(User::factory()->create(['role' => 'admin']))->test('staff.profile-modal')
        ->call('loadDetails')
        ->assertSee('Member since')
        ->assertDontSee('Matches officiated')
        ->assertDontSee('Next match');
});
