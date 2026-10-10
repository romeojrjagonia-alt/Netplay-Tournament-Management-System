<?php

use App\Models\Player;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Volt\Volt;

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => 'http://localhost/auth/google/callback',
    ]);
});

function fakeGoogleUser(array $raw = []): void
{
    $googleUser = (new SocialiteUser)->map([
        'id' => 'google-123',
        'name' => 'Juan Cruz',
        'email' => 'juan@gmail.com',
    ]);

    $googleUser->user = array_merge([
        'email_verified' => true,
        'given_name' => 'Juan',
        'family_name' => 'Cruz',
    ], $raw);

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);
}

test('login and signup pages offer google', function () {
    $this->get(route('login'))->assertSee('Sign in with Google');
    $this->get(route('register'))->assertSee('Sign up with Google');
});

test('the google route redirects to google', function () {
    $this->get(route('auth.google'))
        ->assertRedirectContains('accounts.google.com')
        ->assertRedirectContains('prompt=select_account');
});

test('a user already linked to google is logged in', function () {
    $user = User::factory()->create(['role' => 'player', 'google_id' => 'google-123', 'email' => 'old@example.com']);

    fakeGoogleUser();

    $response = $this->get(route('auth.google.callback'))
        ->assertRedirect(route('player.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);

    // No long-lived "remember me" cookie: closing the browser must end the login.
    $rememberCookies = collect($response->headers->getCookies())
        ->filter(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

    expect($rememberCookies)->toBeEmpty();
});

test('google links to an existing email account and verifies it', function () {
    $user = User::factory()->unverified()->create(['role' => 'umpire', 'email' => 'juan@gmail.com']);

    fakeGoogleUser();

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('umpire.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('google-123');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a new google user is sent to profile setup without being created yet', function () {
    fakeGoogleUser();

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('register'))
        ->assertSessionHas('google_signup', [
            'id' => 'google-123',
            'email' => 'juan@gmail.com',
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
        ]);

    $this->assertGuest();
    expect(User::where('email', 'juan@gmail.com')->exists())->toBeFalse();
});

test('google accounts with an unverified email are rejected', function () {
    fakeGoogleUser(['email_verified' => false]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a failed google exchange returns to login with an error', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('bad state'));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

test('a google signup skips the account step and creates a verified passwordless user', function () {
    $this->withSession(['google_signup' => [
        'id' => 'google-123',
        'email' => 'juan@gmail.com',
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
    ]]);

    Volt::test('auth.register')
        ->assertSet('viaGoogle', true)
        ->assertSee('juan@gmail.com')
        ->call('selectUserType', 'player')
        ->call('nextStep')
        ->assertSet('step', 3)
        ->set('email', 'attacker@example.com')
        ->set('birthDate', '2000-02-04')
        ->set('gender', 'male')
        ->set('purok', 'Purok 5')
        ->set('city', 'Ozamiz City')
        ->set('province', 'Misamis Occidental')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('player.dashboard', absolute: false));

    $user = User::where('google_id', 'google-123')->first();

    expect($user)->not->toBeNull();
    expect($user->email)->toBe('juan@gmail.com');
    expect($user->name)->toBe('Juan Cruz');
    expect($user->password)->toBeNull();
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect(Player::where('user_id', $user->id)->exists())->toBeTrue();
    expect(session('google_signup'))->toBeNull();
    expect(session('verification_prompt'))->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('going back from the profile step returns google users to the role step', function () {
    $this->withSession(['google_signup' => [
        'id' => 'google-123',
        'email' => 'juan@gmail.com',
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
    ]]);

    Volt::test('auth.register')
        ->call('selectUserType', 'player')
        ->call('nextStep')
        ->call('previousStep')
        ->assertSet('step', 1);
});
