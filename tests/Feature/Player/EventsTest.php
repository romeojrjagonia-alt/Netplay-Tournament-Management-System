<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function eventsTournamentCategory(Tournament $tournament, array $categoryOverrides = [], array $tournamentCategoryOverrides = []): TournamentCategory
{
    $category = Category::create(array_merge([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
    ], $categoryOverrides));

    return TournamentCategory::create(array_merge([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ], $tournamentCategoryOverrides));
}

function eventsTournament(array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
        'name' => 'Test Tournament',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(20)->toDateString(),
        'date_end' => now()->addDays(21)->toDateString(),
        'time' => '9:00 AM - 5:00 PM',
        'specific_days' => 'SAT, SUN',
        'registration_start' => '2026-06-01',
        'registration_end' => now()->addDays(10)->toDateString(),
        'status' => 'published',
    ], $overrides));
}

test('events page only lists tournaments accepting registrations', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name]);

    eventsTournament(['name' => 'Open Tournament', 'status' => 'published']);
    eventsTournament(['name' => 'Upcoming Tournament', 'status' => 'draft']);
    eventsTournament(['name' => 'Closed Tournament', 'status' => 'published', 'registration_end' => '2026-01-01']);

    $this->actingAs($user);

    Volt::test('player.events')
        ->assertSee('Open Tournament')
        ->assertDontSee('Upcoming Tournament')
        ->assertDontSee('Closed Tournament');
});

test('search filters tournaments by name', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name]);

    eventsTournament(['name' => 'Inato Tournament']);
    eventsTournament(['name' => 'Barangay Cup']);

    $this->actingAs($user);

    Volt::test('player.events')
        ->set('search', 'Inato')
        ->assertSee('Inato Tournament')
        ->assertDontSee('Barangay Cup');
});

test('viewing a tournament shows its categories', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $tournament = eventsTournament(['name' => 'Inato Tournament']);
    eventsTournamentCategory($tournament, ['name' => "Women's Singles", 'gender' => 'female']);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->assertSee("Women's Singles");
});

test('my applications tab only shows the current player\'s applications', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $otherUser = User::factory()->create(['role' => 'player']);
    $otherPlayer = Player::create(['user_id' => $otherUser->id, 'name' => $otherUser->name]);

    $tournament = eventsTournament(['name' => 'Inato Tournament']);
    $tournamentCategory = eventsTournamentCategory($tournament);

    $myApplication = PlayerApplication::create([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => 'pending_verification',
    ]);
    ApplicationPlayer::create(['application_id' => $myApplication->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);

    $otherApplication = PlayerApplication::create([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => 'payment_pending',
    ]);
    ApplicationPlayer::create(['application_id' => $otherApplication->id, 'player_id' => $otherPlayer->id, 'level_at_apply' => 'Beginner']);

    $this->actingAs($user);

    Volt::test('player.events')
        ->set('tab', 'applications')
        ->assertSee('Waiting for organizers verification')
        ->assertDontSee('Verification confirmed, proceed to payment');
});

test('a player cannot view another player\'s application details', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $otherUser = User::factory()->create(['role' => 'player']);
    $otherPlayer = Player::create(['user_id' => $otherUser->id, 'name' => $otherUser->name]);

    $tournament = eventsTournament();
    $tournamentCategory = eventsTournamentCategory($tournament);

    $otherApplication = PlayerApplication::create([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => 'pending_verification',
    ]);
    ApplicationPlayer::create(['application_id' => $otherApplication->id, 'player_id' => $otherPlayer->id, 'level_at_apply' => 'Beginner']);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewApplication', $otherApplication->id)
        ->assertDontSee('Waiting for organizers verification');
});

test('a verified application shows a proceed to payment link in its details', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);
    $tournamentCategory = eventsTournamentCategory(eventsTournament());

    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'payment_pending']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewApplication', $application->id)
        ->assertSee('Proceed to payment')
        ->call('openPayment')
        ->assertDispatched('modal-show', name: 'application-payment');

    $application->update(['status' => 'pending_verification']);

    Volt::test('player.events')
        ->call('viewApplication', $application->id)
        ->assertDontSee('Proceed to payment');
});

test('the dashboard tournament card links to the events page and shows its poster', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $tournament = eventsTournament(['name' => 'Poster Cup', 'poster_path' => 'posters/poster-cup.jpg']);

    $this->actingAs($user);

    Volt::test('player.dashboard')
        ->assertSee('Poster Cup')
        ->assertSee(route('player.events', ['tournament' => $tournament->id]), false)
        ->assertSee('posters/poster-cup.jpg', false);
});

test('opening the events page from a dashboard card pre-selects that tournament', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $tournament = eventsTournament(['name' => 'Card Cup']);

    $this->actingAs($user)
        ->get(route('player.events', ['tournament' => $tournament->id]))
        ->assertOk()
        ->assertSee("name: 'tournament-details'", false);
});

test('a full category is disabled in the registration dropdown', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    $tournament = eventsTournament();
    $full = eventsTournamentCategory($tournament, ['name' => 'Full Singles'], ['max_slots' => 1]);
    $other = Player::create(['name' => 'Taken Tom']);
    $application = PlayerApplication::create(['tournament_category_id' => $full->id, 'status' => 'approved']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $other->id, 'level_at_apply' => 'Beginner']);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->set('regGender', 'male')
        ->assertSeeHtml('value="'.$full->id.'" disabled')
        ->assertSee('Full Singles (Full)');
});

test('dashboard application rows link to that application on the events page', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);
    $tournamentCategory = eventsTournamentCategory(eventsTournament());

    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'payment_pending']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);

    $this->actingAs($user);

    $url = route('player.events', ['tab' => 'applications', 'application' => $application->id]);

    Volt::test('player.dashboard')->assertSee(e($url), false);

    $this->get($url)
        ->assertOk()
        ->assertSee("name: 'application-details'", false)
        ->assertSee('Proceed to payment');
});

test('an application belonging to another player is not opened from the url', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name]);
    $tournamentCategory = eventsTournamentCategory(eventsTournament());

    $other = Player::create(['name' => 'Someone Else']);
    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'payment_pending']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $other->id, 'level_at_apply' => 'Beginner']);

    $this->actingAs($user)
        ->get(route('player.events', ['application' => $application->id]))
        ->assertOk()
        ->assertDontSee('Proceed to payment');
});

test('a player can delete a rejected application that has no payment proof', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);
    $tournamentCategory = eventsTournamentCategory(eventsTournament());

    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'rejected']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewApplication', $application->id)
        ->assertSee('Delete application')
        ->call('deleteViewingApplication');

    expect(PlayerApplication::find($application->id))->toBeNull();
});

test('a rejected application that has a payment proof cannot be deleted', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);
    $tournamentCategory = eventsTournamentCategory(eventsTournament());

    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'rejected']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);
    PaymentDetail::create([
        'application_id' => $application->id,
        'date_of_payment_made' => now(),
        'reference_number' => '123',
        'payment_status' => 'rejected',
        'proof_of_payment' => 'payment-proofs/x.png',
    ]);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewApplication', $application->id)
        ->assertDontSee('Delete application')
        ->call('deleteViewingApplication')
        ->assertForbidden();

    expect(PlayerApplication::find($application->id))->not->toBeNull();
});

test('a user on another role\'s page is redirected to their own dashboard', function () {
    $this->actingAs(User::factory()->create(['role' => 'player']))
        ->get(route('admin.dashboard'))->assertRedirect(route('player.dashboard'));

    $this->actingAs(User::factory()->create(['role' => 'umpire']))
        ->get(route('player.events'))->assertRedirect(route('umpire.dashboard'));

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('umpire.dashboard'))->assertRedirect(route('admin.dashboard'));
});

test('the dropdown prompt is hidden but not disabled so the browser cannot display an option the form never got', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    $tournament = eventsTournament();
    eventsTournamentCategory($tournament);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->set('regGender', 'male')
        ->assertSeeHtml('<option value="" hidden>Select category</option>')
        ->assertDontSeeHtml('value="" disabled');
});

test('the registration form prefills the account email, not a stale player email', function () {
    $user = User::factory()->create(['role' => 'player', 'email' => 'new@example.com']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'email' => 'old@example.com']);

    $tournament = eventsTournament();

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->call('startRegistration')
        ->assertSet('regEmail', 'new@example.com');
});

test('an admin changing a player account email also updates the player contact email', function () {
    $user = User::factory()->create(['role' => 'player', 'email' => 'old@example.com']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name, 'email' => 'old@example.com']);

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Volt::test('admin.users.index')
        ->call('openEditModal', $user->id)
        ->set('email', 'new@example.com')
        ->call('save');

    expect($player->fresh()->email)->toBe('new@example.com');
});
