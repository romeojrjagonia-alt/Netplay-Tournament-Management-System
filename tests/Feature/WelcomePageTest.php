<?php

use App\Models\Tournament;
use App\Models\User;
use Livewire\Volt\Volt;

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

    // The Register button opens the registration modal instead of leaving the page.
    $this->get('/')
        ->assertSee("open-guest-registration', { tournamentId: {$tournament->id} }", false)
        ->assertDontSee(route('tournaments.register', $tournament), false);
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

test('pages declare the favicon and web manifest', function () {
    $this->get('/')
        ->assertSee('favicon-32x32.png', false)
        ->assertSee('favicon-light.webp', false)
        ->assertSee('site.webmanifest', false);

    expect(public_path('favicon.ico'))->toBeFile()
        ->and(public_path('site.webmanifest'))->toBeFile();
});

test('an active tournament card opens a details view with venue, sport and categories', function () {
    Tournament::create([
        'name' => 'Details Open',
        'format' => 'round_robin',
        'sport' => 'Pickleball',
        'location' => 'JAL Court',
        'scoring_system' => 'Pickleball Best of 3 to 11',
        'date_start' => now()->addDays(20),
        'date_end' => now()->addDays(21),
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addDays(10),
        'status' => 'published',
    ]);

    $this->get('/')
        ->assertSee('View details of Details Open')
        ->assertSee('Pickleball Tournament')
        ->assertSee('JAL Court')
        ->assertSee('Register now');
});

function openTournamentNamed(string $name, array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
        'name' => $name,
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'location' => 'OBC Badminton Facility',
        'date_start' => now()->addDays(10)->toDateString(),
        'date_end' => now()->addDays(12)->toDateString(),
        'registration_start' => now()->subDay()->toDateString(),
        'registration_end' => now()->addDays(5)->toDateString(),
        'status' => 'published',
    ], $overrides));
}

test('explore more opens a list of every tournament open for registration', function () {
    foreach (range(1, 5) as $number) {
        openTournamentNamed("Open Cup $number", ['registration_end' => now()->addDays(5 + $number)->toDateString()]);
    }

    $response = $this->get('/');

    // A button, not a dead link, and the modal it opens.
    $response->assertSee('Explore More')
        ->assertSee('exploreOpen = true', false)
        ->assertSee('Open for registration')
        ->assertSee('5 tournaments accepting registrations right now.')
        ->assertSee('Search tournaments');

    // Only three cards on the page, yet all five are in the list.
    $html = $response->getContent();

    foreach (range(1, 5) as $number) {
        expect(substr_count($html, "Open Cup $number"))->toBeGreaterThanOrEqual(1);
    }

    expect(substr_count($html, 'aria-label="View details of '))->toBe(3)
        ->and(substr_count($html, 'Register by '))->toBe(5);
});

test('each tournament in the explore list has a Register button that opens the registration modal', function () {
    $tournament = openTournamentNamed('Single Cup');

    $this->get('/')
        ->assertSee("exploreOpen = false; \$dispatch('open-guest-registration', { tournamentId: {$tournament->id} })", false);
});

test('the explore list leaves out tournaments that are not accepting registrations', function () {
    openTournamentNamed('Visible Cup');
    openTournamentNamed('Draft Cup', ['status' => 'draft']);
    openTournamentNamed('Closed Cup', ['registration_end' => now()->subDay()->toDateString()]);
    openTournamentNamed('Finished Cup', ['date_start' => now()->subDays(10)->toDateString(), 'date_end' => now()->subDays(8)->toDateString()]);

    $this->get('/')
        ->assertSee('1 tournament accepting registrations right now.')
        ->assertSee('Visible Cup')
        ->assertDontSee('Draft Cup')
        ->assertDontSee('Closed Cup')
        ->assertDontSee('Finished Cup');
});

test('the explore list says so when nothing is open, and has no search for a short list', function () {
    $this->get('/')
        ->assertSee('Explore More')
        ->assertSee('0 tournaments accepting registrations right now.')
        ->assertSee('Coming Soon! Stay Tuned!')
        ->assertDontSee('Search tournaments');
});

test('the registration modal does not close when the user clicks outside it or presses Escape', function () {
    $tournament = openTournamentNamed('Progress Cup');

    $this->get('/')
        ->assertSee('data-modal="guest-registration"', false)
        ->assertSee('disable-click-outside', false)
        ->assertSee('x-on:cancel.prevent', false);
});

test('closing the registration modal asks before discarding progress', function () {
    $tournament = openTournamentNamed('Confirm Cup');

    // The form is filled in once a tournament has been chosen, so render it with one.
    $html = Volt::test('tournaments.register', ['tournament' => $tournament])
        ->assertSee('Discard your registration?')
        ->assertSee('Everything you have entered so far will be lost.')
        ->assertSee('Keep editing')
        ->html();

    expect($html)->toContain("exitGuard('guest-registration')")
        // The X and Cancel both go through the check instead of closing outright.
        ->and(substr_count($html, 'x-on:click="request()"'))->toBeGreaterThanOrEqual(2)
        // Nothing on the form closes the modal directly (only the post-submission link does).
        ->and($html)->not->toContain('$flux.modal(\'guest-registration\').close()');
});
