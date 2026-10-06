<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

test('the prompt opens after signup for an unverified user', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->withSession(['verification_prompt' => 'signup']);

    Volt::test('verify-email-prompt')
        ->assertSet('show', true)
        ->assertSet('reason', 'signup')
        ->assertSee('We will send a verification link to')
        ->assertSee($user->email);
});

test('the prompt opens as a reminder after login and only once', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->withSession(['verification_prompt' => 'login']);

    Volt::test('verify-email-prompt')
        ->assertSet('show', true)
        ->assertSet('reason', 'login');

    Volt::test('verify-email-prompt')
        ->assertSet('show', false);
});

test('the prompt stays closed without a login or signup flag', function () {
    $this->actingAs(User::factory()->unverified()->create());

    Volt::test('verify-email-prompt')
        ->assertSet('show', false);
});

test('the prompt stays closed for verified users', function () {
    $this->actingAs(User::factory()->create())->withSession(['verification_prompt' => 'login']);

    Volt::test('verify-email-prompt')
        ->assertSet('show', false);
});

test('the verification email is only sent when the user clicks verify now', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->withSession(['verification_prompt' => 'signup']);

    $component = Volt::test('verify-email-prompt');

    Notification::assertNothingSent();

    $component->call('sendVerification')->assertSet('sent', true);

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('choosing later closes the prompt without sending an email', function () {
    Notification::fake();

    $this->actingAs(User::factory()->unverified()->create())->withSession(['verification_prompt' => 'login']);

    Volt::test('verify-email-prompt')
        ->call('dismiss')
        ->assertSet('show', false);

    Notification::assertNothingSent();
});

test('verification links are throttled', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    $component = Volt::test('verify-email-prompt');

    $component->call('sendVerification')->call('sendVerification')->assertSet('error', null);

    $component->call('sendVerification')
        ->assertSet('error', 'Please wait a minute before requesting another link.');

    Notification::assertSentToTimes($user, QueuedVerifyEmail::class, 2);
});

test('the verification notification is queued', function () {
    expect(new QueuedVerifyEmail)->toBeInstanceOf(ShouldQueue::class);
});
