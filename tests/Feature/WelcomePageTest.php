<?php

use App\Models\Tournament;

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
