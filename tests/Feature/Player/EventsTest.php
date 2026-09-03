<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
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
        'date_start' => '2026-07-01',
        'date_end' => '2026-07-31',
        'time' => '9:00 AM - 5:00 PM',
        'specific_days' => 'SAT, SUN',
        'registration_start' => '2026-06-01',
        'registration_end' => '2026-12-31',
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
