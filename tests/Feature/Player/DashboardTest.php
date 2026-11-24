<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function playerDashTournament(string $name, array $overrides = []): TournamentCategory
{
    $tournament = Tournament::create(array_merge([
        'name' => $name,
        'format' => 'round_robin',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(10)->toDateString(),
        'date_end' => now()->addDays(12)->toDateString(),
        'registration_start' => now()->subDay()->toDateString(),
        'registration_end' => now()->addDays(5)->toDateString(),
        'status' => 'published',
    ], $overrides));

    $category = Category::firstOrCreate(['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1, 'level' => 'Level C']);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 16,
        'status' => 'open',
    ]);
}

function playerDashUser(): array
{
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => 'Mark Santos']);

    return [$user, $player];
}

function playerDashApply(Player $player, TournamentCategory $tournamentCategory, string $status, array $extra = []): PlayerApplication
{
    $application = PlayerApplication::create(array_merge([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => $status,
    ], $extra));

    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Level C']);

    return $application;
}

it('shows the player their real applications and their state', function () {
    [$user, $player] = playerDashUser();

    $waiting = playerDashTournament('Waiting Open');
    $paying = playerDashTournament('Paying Open');
    $rejected = playerDashTournament('Rejected Open');

    playerDashApply($player, $waiting, 'pending_verification');
    $payment = playerDashApply($player, $paying, 'payment_pending');
    playerDashApply($player, $rejected, 'rejected', ['rejection_reason' => 'Wrong age group']);

    Volt::actingAs($user)->test('player.dashboard')
        ->assertSee('Waiting Open')
        ->assertSee('Waiting for organizers verification')
        ->assertSee('Verification confirmed, proceed to payment')
        ->assertSee($payment->reference_code)
        ->assertSee('Wrong age group')
        ->assertDontSee('Inato Tournament');
});

it('lists open tournaments and the next confirmed entry', function () {
    [$user, $player] = playerDashUser();

    $open = playerDashTournament('Open For All');
    playerDashTournament('Draft Hidden', ['status' => 'draft']);
    playerDashApply($player, $open, 'approved');

    Volt::actingAs($user)->test('player.dashboard')
        ->assertSee('UPCOMING TOURNAMENT')
        ->assertSee('Open For All')
        ->assertDontSee('Draft Hidden');
});

it('handles a player with no applications or player record', function () {
    $user = User::factory()->create(['role' => 'player']);

    Volt::actingAs($user)->test('player.dashboard')
        ->assertSee('You have no tournament applications in progress.');
});
