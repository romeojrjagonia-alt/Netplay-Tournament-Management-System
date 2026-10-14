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

function scoringTournamentCategory(): TournamentCategory
{
    $tournament = Tournament::create([
        'name' => 'Inato Tournament',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-07-01',
        'date_end' => '2026-07-31',
        'time' => '9:00 AM - 5:00 PM',
        'registration_start' => '2026-06-01',
        'registration_end' => '2026-12-31',
        'status' => 'published',
    ]);

    $category = Category::create([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
    ]);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);
}

/**
 * @return array{0: Matches, 1: PlayerApplication, 2: PlayerApplication, 3: Player, 4: Player}
 */
function scoringMatchWithPlayers(User $umpire): array
{
    $tournamentCategory = scoringTournamentCategory();

    $playerA = Player::create(['name' => 'Mark Santos']);
    $playerB = Player::create(['name' => 'Juan Torres']);

    $applicationA = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
    ApplicationPlayer::create(['application_id' => $applicationA->id, 'player_id' => $playerA->id, 'level_at_apply' => 'Beginner']);

    $applicationB = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
    ApplicationPlayer::create(['application_id' => $applicationB->id, 'player_id' => $playerB->id, 'level_at_apply' => 'Beginner']);

    $match = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 2',
        'assigned_umpire_id' => $umpire->id,
    ]);

    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationB->id]);

    return [$match, $applicationA, $applicationB, $playerA, $playerB];
}

test('a non-umpire cannot view the scoring page', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match] = scoringMatchWithPlayers($umpire);

    $player = User::factory()->create(['role' => 'player']);
    $this->actingAs($player);

    Volt::test('umpire.matches.scoring', ['match' => $match])->assertForbidden();
});

test('an umpire not assigned to this match cannot view its scoring page', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match] = scoringMatchWithPlayers($umpire);

    $otherUmpire = User::factory()->create(['role' => 'umpire']);
    $this->actingAs($otherUmpire);

    Volt::test('umpire.matches.scoring', ['match' => $match])->assertForbidden();
});

test('starting the match moves it to in progress and creates the first set', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match] = scoringMatchWithPlayers($umpire);

    $this->actingAs($umpire);

    Volt::test('umpire.matches.scoring', ['match' => $match])
        ->call('startMatch');

    expect($match->fresh()->status)->toBe('in_progress');
    expect(MatchGame::where('match_id', $match->id)->count())->toBe(1);
});

test('a match seeded as in_progress with no sets yet can still start its first set', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match] = scoringMatchWithPlayers($umpire);
    $match->update(['status' => 'in_progress']);

    $this->actingAs($umpire);

    Volt::test('umpire.matches.scoring', ['match' => $match])
        ->assertSet('canStartMatch', true)
        ->call('startMatch');

    expect($match->fresh()->status)->toBe('in_progress');
    expect(MatchGame::where('match_id', $match->id)->count())->toBe(1);
});

test('recording a rally increments the scoring player\'s team score', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match, , , $playerA] = scoringMatchWithPlayers($umpire);

    $this->actingAs($umpire);

    Volt::test('umpire.matches.scoring', ['match' => $match])
        ->call('startMatch')
        ->call('recordRally', $playerA->id)
        ->assertSet('currentScores', ['A' => 1, 'B' => 0]);
});

test('a game is automatically decided once a side reaches 21 with a 2 point lead', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match, $applicationA, , $playerA, $playerB] = scoringMatchWithPlayers($umpire);

    $this->actingAs($umpire);

    $component = Volt::test('umpire.matches.scoring', ['match' => $match])
        ->call('startMatch');

    $game = MatchGame::where('match_id', $match->id)->first();

    for ($i = 1; $i <= 19; $i++) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerB->id, 'rally_number' => $i]);
    }
    for ($i = 20; $i <= 39; $i++) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerA->id, 'rally_number' => $i]);
    }

    // 19 for B, 20 for A so far — one more for A makes it 21-19.
    $component->call('recordRally', $playerA->id);

    expect($game->fresh()->winner_application_id)->toBe($applicationA->id);
    $component->assertSet('canAddSet', true);
});

test('undoing the winning rally reopens the set', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match, , , $playerA, $playerB] = scoringMatchWithPlayers($umpire);

    $this->actingAs($umpire);

    $component = Volt::test('umpire.matches.scoring', ['match' => $match])->call('startMatch');
    $game = MatchGame::where('match_id', $match->id)->first();

    for ($i = 1; $i <= 20; $i++) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerA->id, 'rally_number' => $i]);
    }
    for ($i = 21; $i <= 39; $i++) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerB->id, 'rally_number' => $i]);
    }

    $component->call('recordRally', $playerA->id);
    expect($game->fresh()->winner_application_id)->not->toBeNull();

    $component->call('undoLastRally');
    expect($game->fresh()->winner_application_id)->toBeNull();
    expect(Rally::where('match_game_id', $game->id)->where('scoring_player_id', $playerA->id)->count())->toBe(20);

    $component->call('redoLastRally');
    expect($game->fresh()->winner_application_id)->not->toBeNull();
});

test('confirming results is only allowed once a side has won 2 sets, and completes the match', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match, $applicationA, , $playerA, $playerB] = scoringMatchWithPlayers($umpire);

    $this->actingAs($umpire);

    $component = Volt::test('umpire.matches.scoring', ['match' => $match])->call('startMatch');

    // Win set 1 for A.
    $game1 = MatchGame::where('match_id', $match->id)->first();
    for ($i = 1; $i <= 21; $i++) {
        Rally::create(['match_game_id' => $game1->id, 'scoring_player_id' => $playerA->id, 'rally_number' => $i]);
    }
    $component->call('recordRally', $playerB->id); // triggers a recompute; harmless extra point for B, doesn't change A's win.

    $component->assertSet('canConfirmResults', false);

    $component->call('addSet');
    $game2 = MatchGame::where('match_id', $match->id)->where('game_number', 2)->first();
    expect($game2)->not->toBeNull();

    for ($i = 1; $i <= 21; $i++) {
        Rally::create(['match_game_id' => $game2->id, 'scoring_player_id' => $playerA->id, 'rally_number' => $i]);
    }
    $component->call('recordRally', $playerB->id);

    $component->assertSet('canConfirmResults', true)
        ->call('confirmResults');

    expect($match->fresh()->status)->toBe('completed');
    expect($match->fresh()->winner_application_id)->toBe($applicationA->id);
});

test('line judge names are saved as they are entered', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match] = scoringMatchWithPlayers($umpire);

    $this->actingAs($umpire);

    Volt::test('umpire.matches.scoring', ['match' => $match])
        ->set('lineJudge1Name', 'Pedro Reyes')
        ->set('lineJudge2Name', 'Ana Cruz');

    expect($match->fresh()->line_judge_1_name)->toBe('Pedro Reyes');
    expect($match->fresh()->line_judge_2_name)->toBe('Ana Cruz');
});

test('the rally score sheet is not rendered until the umpire opens it', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match] = scoringMatchWithPlayers($umpire);

    $this->actingAs($umpire);

    Volt::test('umpire.matches.scoring', ['match' => $match])
        ->assertSet('showScoresheet', false)
        ->assertDontSee('UMPIRE')
        ->call('openScoresheet')
        ->assertSet('showScoresheet', true)
        ->assertSee('UMPIRE')
        ->call('closeScoresheet')
        ->assertSet('showScoresheet', false)
        ->assertDontSee('UMPIRE');
});

test('in a 31-point tournament the first side to reach 31 wins the only set, and the match waits for confirmation', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match, $applicationA, , $playerA, $playerB] = scoringMatchWithPlayers($umpire);
    $match->tournamentCategory->tournament->update(['scoring_system' => '31-Point Single Set']);

    $this->actingAs($umpire);

    $component = Volt::test('umpire.matches.scoring', ['match' => $match->fresh()])
        ->call('startMatch');
    $game = MatchGame::where('match_id', $match->id)->first();

    // 30-29: no 2-point-lead or deuce rule, so the game is still going.
    for ($i = 1; $i <= 29; $i++) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerB->id, 'rally_number' => $i]);
    }
    for ($i = 30; $i <= 59; $i++) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerA->id, 'rally_number' => $i]);
    }

    $component->call('recordRally', $playerB->id) // 30-30
        ->assertSet('canConfirmResults', false);

    $component->call('recordRally', $playerA->id); // 31-30

    expect($game->fresh()->winner_application_id)->toBe($applicationA->id);
    expect($match->fresh()->status)->toBe('in_progress');

    $component->assertSet('canAddSet', false)
        ->assertSet('canConfirmResults', true)
        ->call('confirmResults');

    expect($match->fresh()->status)->toBe('completed');
    expect($match->fresh()->winner_application_id)->toBe($applicationA->id);
    expect(MatchGame::where('match_id', $match->id)->count())->toBe(1);
});

test('a 21-point tournament is unaffected by the single set rules', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    [$match, , , $playerA] = scoringMatchWithPlayers($umpire);

    $this->actingAs($umpire);

    $component = Volt::test('umpire.matches.scoring', ['match' => $match])->call('startMatch');
    $game = MatchGame::where('match_id', $match->id)->first();

    for ($i = 1; $i <= 21; $i++) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerA->id, 'rally_number' => $i]);
    }

    $component->call('recordRally', $playerA->id)
        ->assertSet('canAddSet', true)
        ->assertSet('canConfirmResults', false);
});
