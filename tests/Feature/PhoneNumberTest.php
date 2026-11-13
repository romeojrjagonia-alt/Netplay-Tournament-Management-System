<?php

use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Livewire\Volt\Volt;

it('accepts an 11-digit mobile number starting with 09', function (string $number) {
    expect(Validator::make(['phone' => $number], ['phone' => [new PhoneNumber]])->passes())->toBeTrue();
})->with(['09123456789', '09171234567', '0912 345 6789', '0912-345-6789']);

it('rejects numbers that are too long, too short or in the wrong format', function (string $number) {
    $validator = Validator::make(['phone' => $number], ['phone' => [new PhoneNumber]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('phone'))->toBe('The phone must be an 11-digit mobile number starting with 09, e.g. 09123456789.');
})->with([
    'too many digits' => '091234567890123',
    'one digit too many' => '091234567891',
    'too few digits' => '0912345678',
    'does not start with 09' => '12345678901',
    'international format' => '+639123456789',
    'letters' => '0912abc6789',
    'only a prefix' => '09',
]);

it('limits every phone field to 11 digits and a numeric keypad', function () {
    view()->share('errors', new ViewErrorBag);

    $player = Blade::render('<x-player.registration-field label="Phone" model="regPhoneNumber" />');
    $auth = Blade::render('<x-auth.field label="Phone Number" model="phoneNumber" type="tel" />');
    $other = Blade::render('<x-player.registration-field label="Name" model="name" />');

    foreach ([$player, $auth] as $html) {
        expect($html)
            ->toContain('maxlength="11"')
            ->toContain('inputmode="numeric"')
            ->toContain('type="tel"')
            ->toContain("replace(/\\D/g, '')");
    }

    expect($other)->not->toContain('maxlength="11"')->not->toContain('inputmode="numeric"');
});

it('rejects a long phone number when signing up', function () {
    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->set('firstName', 'Juan')
        ->set('lastName', 'Dela Cruz')
        ->set('email', 'juan@example.com')
        ->set('password', 'Password123')
        ->set('password_confirmation', 'Password123')
        ->set('birthDate', '1999-05-05')
        ->set('gender', 'male')
        ->set('purok', 'Purok 5')
        ->set('city', 'Ozamiz City')
        ->set('province', 'Misamis Occidental')
        ->set('phoneNumber', '091234567890123')
        ->call('register')
        ->assertHasErrors(['phoneNumber']);

    expect(User::where('email', 'juan@example.com')->exists())->toBeFalse();
});

it('shows the phone error on sign-up and clears it once the number is right', function () {
    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->call('register')
        ->assertHasErrors('phoneNumber')
        ->set('phoneNumber', '0912345')
        ->assertHasErrors('phoneNumber')
        ->set('phoneNumber', '09123456789')
        ->assertHasNoErrors('phoneNumber');
});

it('checks the phone number on the tournament registration form', function () {
    $tournament = Tournament::create([
        'name' => 'Phone Open',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(10)->toDateString(),
        'date_end' => now()->addDays(11)->toDateString(),
        'registration_start' => now()->subDay()->toDateString(),
        'registration_end' => now()->addDays(5)->toDateString(),
        'status' => 'published',
    ]);

    Volt::test('tournaments.register', ['tournament' => $tournament])
        ->set('regPhoneNumber', '09123456789012')
        ->call('nextStep')
        ->assertHasErrors('regPhoneNumber')
        ->set('regPhoneNumber', '09123456789')
        ->assertHasNoErrors('regPhoneNumber')
        ->set('pairPhoneNumber', '123')
        ->set('regEmail', 'juan@example.com')
        ->assertHasNoErrors('regEmail');
});

it('checks the phone number in the player profile', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    Volt::actingAs($user)->test('player.profile-modal')
        ->call('startEditing')
        ->set('phoneNumber', '0912345678901')
        ->call('save')
        ->assertHasErrors('phoneNumber')
        ->set('phoneNumber', '09123456789')
        ->call('save')
        ->assertHasNoErrors('phoneNumber');
});

it('leaves an optional phone number empty without complaint', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    Volt::actingAs($user)->test('player.profile-modal')
        ->call('startEditing')
        ->set('phoneNumber', '')
        ->call('save')
        ->assertHasNoErrors('phoneNumber');
});
