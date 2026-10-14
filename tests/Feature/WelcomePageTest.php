<?php

use App\Models\Tournament;
use App\Models\User;

test('welcome page renders the landing page sections', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('NetPlay');
    $response->assertSee('Annual Tournaments');
    $response->assertSee('Active Tournaments');
    $response->assertDontSee('FEATURED PLAYERS');
});

test('welcome page keeps a fixed navbar', function () {
    $this->get('/')->assertSee('fixed inset-x-0 top-0', false);
});

test('welcome page shows tournaments accepting registrations as cards', function () {
    $tournament = Tournament::create([
        'name' => 'Open Smash Cup',
        'format' => 'single_elimination',
        'sport' => 'badminton',
        'scoring_system' => 'rally',
        'location' => 'JALS Badminton Court',
        'date_start' => now()->addDays(10),
        'date_end' => now()->addDays(12),
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addDays(5),
        'status' => 'published',
    ]);

    $this->get('/')
        ->assertSee($tournament->name)
        ->assertSee('JALS Badminton Court');
});

test('active tournament register button links to the public registration form', function () {
    $tournament = Tournament::create([
        'name' => 'Direct Register Cup',
        'format' => 'single_elimination',
        'sport' => 'badminton',
        'scoring_system' => 'rally',
        'date_start' => now()->addDays(10),
        'date_end' => now()->addDays(12),
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addDays(5),
        'status' => 'published',
    ]);

    $this->get('/')->assertSee(route('tournaments.register', $tournament), false);
});

test('guests see sign in and sign up on the landing page', function () {
    $this->get('/')
        ->assertSee('Sign In')
        ->assertSee('Sign Up')
        ->assertDontSee('Go to Dashboard');
});

test('signed in users see a dashboard link and sign out instead of sign in', function () {
    $user = User::factory()->create(['role' => 'player']);

    $this->actingAs($user)
        ->get('/')
        ->assertSee('Go to Dashboard')
        ->assertSee('Sign Out')
        ->assertDontSee('Sign In')
        ->assertDontSee('REGISTER NOW!')
        ->assertSee(route('player.dashboard'), false);
});

test('the session lifetime defaults to 30 minutes', function () {
    expect((int) config('session.lifetime'))->toBe(30);
});

test('the navbar links to the active tournaments and the application progress check', function () {
    $this->get('/')
        ->assertSee('href="#tournaments"', false)
        ->assertSee('href="#check-application"', false)
        ->assertSee('Check Tournament Application');
});
