<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Rally;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function matchesTournamentCategory(array $categoryOverrides = []): TournamentCategory
{
    $tournament = Tournament::create([
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
    ]);

    $category = Category::create(array_merge([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
    ], $categoryOverrides));

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);
}

function matchesApplicationFor(TournamentCategory $tournamentCategory, Player $player): PlayerApplication
{
    $application = PlayerApplication::create([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => 'approved',
    ]);

    ApplicationPlayer::create([
        'application_id' => $application->id,
        'player_id' => $player->id,
        'level_at_apply' => 'Beginner',
    ]);

    return $application;
}

function matchesMatch(TournamentCategory $tournamentCategory, array $overrides = []): Matches
{
    return Matches::create(array_merge([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 1',
    ], $overrides));
}

test('my matches page only shows the current player\'s matches', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $tournamentCategory = matchesTournamentCategory();
    $myApplication = matchesApplicationFor($tournamentCategory, $player);
    $opponentApplication = matchesApplicationFor($tournamentCategory, Player::create(['name' => 'Opponent Player']));

    $match = matchesMatch($tournamentCategory, ['court' => 'Court 1']);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $myApplication->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $opponentApplication->id]);

    $otherTournamentCategory = matchesTournamentCategory(['name' => "Women's Singles", 'gender' => 'female']);
    $strangerApplication = matchesApplicationFor($otherTournamentCategory, Player::create(['name' => 'Stranger One']));
    $anotherApplication = matchesApplicationFor($otherTournamentCategory, Player::create(['name' => 'Stranger Two']));

    $unrelatedMatch = matchesMatch($otherTournamentCategory, ['court' => 'Court 9']);
    MatchApplication::create(['match_id' => $unrelatedMatch->id, 'application_id' => $strangerApplication->id]);
    MatchApplication::create(['match_id' => $unrelatedMatch->id, 'application_id' => $anotherApplication->id]);

    $this->actingAs($user);

    Volt::test('player.matches')
        ->assertSee($player->name)
        ->assertSee('Opponent Player')
        ->assertDontSee('Stranger One')
        ->assertDontSee('Stranger Two');
});

test('filter tabs only show matches in the relevant status bucket', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $tournamentCategory = matchesTournamentCategory();
    $myApplication = matchesApplicationFor($tournamentCategory, $player);
    $opponentApplication = matchesApplicationFor($tournamentCategory, Player::create(['name' => 'Rival Player']));

    $upcomingMatch = matchesMatch($tournamentCategory, [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 1',
    ]);
    MatchApplication::create(['match_id' => $upcomingMatch->id, 'application_id' => $myApplication->id]);
    MatchApplication::create(['match_id' => $upcomingMatch->id, 'application_id' => $opponentApplication->id]);

    $completedMatch = matchesMatch($tournamentCategory, [
        'round' => 2,
        'winner_application_id' => $myApplication->id,
        'status' => 'completed',
        'scheduled_at' => now()->subDay(),
        'court' => 'Court 2',
    ]);
    MatchApplication::create(['match_id' => $completedMatch->id, 'application_id' => $myApplication->id]);
    MatchApplication::create(['match_id' => $completedMatch->id, 'application_id' => $opponentApplication->id]);

    $this->actingAs($user);

    Volt::test('player.matches')
        ->set('tab', 'upcoming')
        ->assertSee('Court 1')
        ->assertDontSee('Court 2');

    Volt::test('player.matches')
        ->set('tab', 'concluded')
        ->assertSee('Court 2')
        ->assertDontSee('Court 1');
});

test('a completed match shows win or lose from the current player\'s perspective', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $tournamentCategory = matchesTournamentCategory();
    $myApplication = matchesApplicationFor($tournamentCategory, $player);
    $opponentApplication = matchesApplicationFor($tournamentCategory, Player::create(['name' => 'Rival Player']));

    $match = matchesMatch($tournamentCategory, [
        'winner_application_id' => $opponentApplication->id,
        'status' => 'completed',
        'scheduled_at' => now()->subDay(),
    ]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $myApplication->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $opponentApplication->id]);

    MatchGame::create(['match_id' => $match->id, 'game_number' => 1, 'winner_application_id' => $opponentApplication->id]);
    MatchGame::create(['match_id' => $match->id, 'game_number' => 2, 'winner_application_id' => $opponentApplication->id]);

    $this->actingAs($user);

    Volt::test('player.matches')
        ->assertSee('LOSE')
        ->assertSee('Winner')
        ->assertSee('Rival Player')
        ->assertSee('2 sets played');
});

function matchesScoreRallies(MatchGame $game, Player $leader, int $leaderPoints, Player $trailer, int $trailerPoints): void
{
    $number = 1;

    foreach (range(1, $leaderPoints) as $ignored) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $leader->id, 'rally_number' => $number++]);
    }

    foreach (range(1, $trailerPoints) as $ignored) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $trailer->id, 'rally_number' => $number++]);
    }
}

test('an in-progress match shows its live score at the top of upcoming matches', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);
    $rival = Player::create(['name' => 'Rival Player']);

    $tournamentCategory = matchesTournamentCategory();
    $myApplication = matchesApplicationFor($tournamentCategory, $player);
    $rivalApplication = matchesApplicationFor($tournamentCategory, $rival);

    $match = matchesMatch($tournamentCategory, ['status' => 'in_progress', 'court' => 'Court 7']);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $myApplication->id, 'slot' => 1]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $rivalApplication->id, 'slot' => 2]);

    $finished = MatchGame::create(['match_id' => $match->id, 'game_number' => 1, 'winner_application_id' => $myApplication->id]);
    matchesScoreRallies($finished, $player, 21, $rival, 17);

    $current = MatchGame::create(['match_id' => $match->id, 'game_number' => 2]);
    matchesScoreRallies($current, $player, 9, $rival, 12);

    $this->actingAs($user);

    Volt::test('player.matches')
        ->set('tab', 'upcoming')
        ->assertSee('LIVE')
        ->assertSee('Court 7')
        ->assertSeeInOrder(['Game', '2', 'in progress'])
        ->assertSeeInOrder(['Games won', '1 - 0'])
        ->assertSee('G1: 21-17')
        ->assertSee('>9</span>', false)
        ->assertSee('>12</span>', false);

    Volt::test('player.matches')
        ->set('tab', 'concluded')
        ->assertDontSee('Court 7');
});

test('a concluded match shows the winner, sets played and each game score', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);
    $rival = Player::create(['name' => 'Rival Player']);

    $tournamentCategory = matchesTournamentCategory();
    $myApplication = matchesApplicationFor($tournamentCategory, $player);
    $rivalApplication = matchesApplicationFor($tournamentCategory, $rival);

    $match = matchesMatch($tournamentCategory, [
        'status' => 'completed',
        'winner_application_id' => $myApplication->id,
        'scheduled_at' => now()->subDay(),
    ]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $myApplication->id, 'slot' => 1]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $rivalApplication->id, 'slot' => 2]);

    $gameOne = MatchGame::create(['match_id' => $match->id, 'game_number' => 1, 'winner_application_id' => $myApplication->id]);
    matchesScoreRallies($gameOne, $player, 21, $rival, 15);
    $gameTwo = MatchGame::create(['match_id' => $match->id, 'game_number' => 2, 'winner_application_id' => $rivalApplication->id]);
    matchesScoreRallies($gameTwo, $player, 18, $rival, 21);
    $gameThree = MatchGame::create(['match_id' => $match->id, 'game_number' => 3, 'winner_application_id' => $myApplication->id]);
    matchesScoreRallies($gameThree, $player, 21, $rival, 19);

    $this->actingAs($user);

    Volt::test('player.matches')
        ->set('tab', 'concluded')
        ->assertSee('WIN')
        ->assertSeeInOrder(['Winner', $player->name])
        ->assertSee('3 sets played')
        ->assertSee('G1: 21-15')
        ->assertSee('G2: 18-21')
        ->assertSee('G3: 21-19');
});
