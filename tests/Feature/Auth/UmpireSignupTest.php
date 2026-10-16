<?php

use App\Mail\UmpireSignupCode;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;

function umpireWizardReadyToRegister(): Testable
{
    return Volt::test('auth.register')
        ->call('selectUserType', 'umpire')
        ->call('nextStep')
        ->set('firstName', 'Pedro')
        ->set('lastName', 'Reyes')
        ->set('email', 'pedro@example.com')
        ->set('password', 'Password123')
        ->set('password_confirmation', 'Password123')
        ->call('nextStep')
        ->set('birthDate', '1990-02-04')
        ->set('phoneNumber', '09171234567')
        ->set('gender', 'male')
        ->set('purok', 'Purok 5')
        ->set('city', 'Ozamiz City')
        ->set('province', 'Misamis Occidental');
}

function lastUmpireCode(): string
{
    $code = null;

    Mail::assertSent(UmpireSignupCode::class, function (UmpireSignupCode $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

test('an umpire gets a code by email and no account exists yet', function () {
    Mail::fake();

    umpireWizardReadyToRegister()
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('awaitingCode', true)
        ->assertSee('Verify your email');

    Mail::assertSent(UmpireSignupCode::class, fn ($mail) => $mail->hasTo('pedro@example.com'));

    expect(User::where('email', 'pedro@example.com')->exists())->toBeFalse()
        ->and(Player::count())->toBe(0);

    $this->assertGuest();
});

test('the correct code creates a verified umpire and signs them in', function () {
    Mail::fake();

    $component = umpireWizardReadyToRegister()->call('register');

    $component->set('verificationCode', lastUmpireCode())
        ->call('verifyCode')
        ->assertHasNoErrors()
        ->assertRedirect(route('umpire.dashboard', absolute: false));

    $user = User::where('email', 'pedro@example.com')->firstOrFail();

    expect($user->role)->toBe('umpire')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->player)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('a wrong code is rejected and creates nothing', function () {
    Mail::fake();

    umpireWizardReadyToRegister()
        ->call('register')
        ->set('verificationCode', '000000')
        ->call('verifyCode')
        ->assertHasErrors('verificationCode')
        ->assertSet('awaitingCode', true);

    expect(User::where('email', 'pedro@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('too many wrong codes discards the sign-up', function () {
    Mail::fake();

    $component = umpireWizardReadyToRegister()->call('register');

    foreach (range(1, 5) as $attempt) {
        $component->set('verificationCode', '000000')->call('verifyCode');
    }

    $component->assertSet('awaitingCode', false)
        ->assertSet('step', 1)
        ->assertDispatched('system-message', variant: 'danger');

    expect(User::where('email', 'pedro@example.com')->exists())->toBeFalse();
});

test('cancelling the verification creates no account and restarts the form', function () {
    Mail::fake();

    umpireWizardReadyToRegister()
        ->call('register')
        ->call('cancelVerification')
        ->assertSet('awaitingCode', false)
        ->assertSet('step', 1)
        ->assertSet('email', '');

    expect(User::count())->toBe(0);
});

test('a code can only be resent after sixty seconds', function () {
    Mail::fake();

    $component = umpireWizardReadyToRegister()->call('register');

    $component->call('resendCode')->assertHasErrors('verificationCode');
    Mail::assertSent(UmpireSignupCode::class, 1);

    $this->travel(61)->seconds();

    $component->call('resendCode')->assertHasNoErrors();
    Mail::assertSent(UmpireSignupCode::class, 2);
});

test('an old code stops working once a new one is sent', function () {
    Mail::fake();

    $component = umpireWizardReadyToRegister()->call('register');
    $firstCode = lastUmpireCode();

    $this->travel(61)->seconds();
    $component->call('resendCode');

    $sent = Mail::sent(UmpireSignupCode::class);
    $newCode = $sent->last()->code;

    if ($firstCode === $newCode) {
        $this->markTestSkipped('Random codes collided.');
    }

    $component->set('verificationCode', $firstCode)->call('verifyCode')->assertHasErrors('verificationCode');
    $component->set('verificationCode', $newCode)->call('verifyCode')->assertHasNoErrors();

    expect(User::where('email', 'pedro@example.com')->exists())->toBeTrue();
});

test('an expired code is refused', function () {
    Mail::fake();

    $component = umpireWizardReadyToRegister()->call('register');
    $code = lastUmpireCode();

    $this->travel(11)->minutes();

    $component->set('verificationCode', $code)->call('verifyCode')->assertHasErrors('verificationCode');

    expect(User::where('email', 'pedro@example.com')->exists())->toBeFalse();
});

test('players still register without a code', function () {
    Mail::fake();

    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->call('nextStep')
        ->set('firstName', 'Juan')
        ->set('lastName', 'Cruz')
        ->set('email', 'juan@example.com')
        ->set('password', 'Password123')
        ->set('password_confirmation', 'Password123')
        ->call('nextStep')
        ->set('birthDate', '2000-02-04')
        ->set('phoneNumber', '09171234567')
        ->set('gender', 'male')
        ->set('purok', 'Purok 5')
        ->set('city', 'Ozamiz City')
        ->set('province', 'Misamis Occidental')
        ->call('register')
        ->assertSet('awaitingCode', false)
        ->assertRedirect(route('player.dashboard', absolute: false));

    Mail::assertNothingSent();
});

test('the login and sign-up pages render as modals over the landing page', function () {
    $this->get('/login')->assertOk()->assertSee('bg-black/70', false)->assertSee('Sign In');
    $this->get('/register')->assertOk()->assertSee('bg-black/70', false)->assertSee('Create Your Account');
});
