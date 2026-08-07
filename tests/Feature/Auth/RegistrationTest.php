<?php

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
        ->assertSet('step', 2);
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
