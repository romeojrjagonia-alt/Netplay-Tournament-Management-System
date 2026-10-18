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

function scoresheetMatch(User $umpire): Matches
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

    $category = Category::create(['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1]);

    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);

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
        'status' => 'in_progress',
        'scheduled_at' => now()->addDay(),
        'court' => 'Court 2',
        'assigned_umpire_id' => $umpire->id,
        'line_judge_1_name' => 'Pedro Reyes',
    ]);

    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationB->id]);

    $game = MatchGame::create(['match_id' => $match->id, 'game_number' => 1]);
    Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerA->id, 'rally_number' => 1]);
    Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerB->id, 'rally_number' => 2]);

    return $match;
}

test('a non-umpire cannot view the scoresheet', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    $player = User::factory()->create(['role' => 'player']);
    $this->actingAs($player);

    $this->get(route('umpire.matches.scoresheet', $match))->assertRedirect(route('player.dashboard'));
});

test('an umpire not assigned to the match cannot view its scoresheet', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    $otherUmpire = User::factory()->create(['role' => 'umpire']);
    $this->actingAs($otherUmpire);

    $this->get(route('umpire.matches.scoresheet', $match))->assertForbidden();
});

test('the assigned umpire can view the scoresheet with the match, players, and officials', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    $this->actingAs($umpire);

    $this->get(route('umpire.matches.scoresheet', $match))
        ->assertOk()
        ->assertSee('Mark Santos')
        ->assertSee('Juan Torres')
        ->assertSee('Pedro Reyes')
        ->assertSee('Court 2');
});

test('the download route returns an attachment with a Word content type', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    $this->actingAs($umpire);

    $response = $this->get(route('umpire.matches.scoresheet.download', $match));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/msword');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('Match-'.$match->id.'-Scoresheet.doc');
    $response->assertSee('Mark Santos', false);
});

test('the printed scoresheet follows the paper form layout', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    $this->actingAs($umpire);

    $this->get(route('umpire.matches.scoresheet', $match))
        ->assertOk()
        ->assertSee('Inato Tournament')
        ->assertSee('July 1-31, 2026')
        ->assertSee(['GAME NO:', 'Set: 1', 'COURT:', 'BRACKET:', 'CATEGORY:', 'TIME:', 'Team 1', 'Team 2'])
        ->assertSee(['UMPIRE:', 'Signature', 'WINNER:'], false);
});

test('the downloaded document uses the same layout with a landscape page and one black cell per rally won', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    $this->actingAs($umpire);

    $response = $this->get(route('umpire.matches.scoresheet.download', $match))
        ->assertOk()
        ->assertSee('INATO TOURNAMENT', false)
        ->assertSee(['GAME NO:', 'Set: 1', 'COURT:', 'BRACKET:', 'CATEGORY:', 'TIME:', 'Team 1', 'Team 2', 'UMPIRE:', 'Signature', 'WINNER:'], false)
        ->assertSee('mso-page-orientation: landscape', false);

    // Two rallies so far (one won by each player): exactly two black cells.
    expect(substr_count($response->getContent(), 'bgcolor="#000000"'))->toBe(2);
});

test('each team\'s black cells are numbered by that team\'s points, in the row of the player who scored them', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    $set = $match->scoresheetSets()[0];
    $playerA = $match->sidePlayers('A')[0];
    $playerB = $match->sidePlayers('B')[0];

    expect($set['scorers'])->toBe(['A' => [1 => $playerA['id']], 'B' => [1 => $playerB['id']]]);
    expect($set['winner'])->toBeNull();
});

test('every set prints on its own sheet, and an unplayed match still prints a blank one', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);
    MatchGame::create(['match_id' => $match->id, 'game_number' => 2]);

    expect($match->fresh()->scoresheetSets())->toHaveCount(2);

    $this->actingAs($umpire);
    $this->get(route('umpire.matches.scoresheet', $match))->assertSee(['Set: 1', 'Set: 2']);

    $blank = scoresheetMatch($umpire);
    $blank->games()->delete();

    expect($blank->fresh()->scoresheetSets())->toHaveCount(1);
    expect($blank->fresh()->scoresheetSets()[0]['scorers'])->toBe(['A' => [], 'B' => []]);
});

test('the grid stays at 31 points per team: later points are not printed', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);
    $game = $match->games()->first();
    $player = $match->sidePlayers('A')[0];

    foreach (range(3, 42) as $n) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $player['id'], 'rally_number' => $n]);
    }

    $set = $match->fresh()->scoresheetSets()[0];

    expect(array_keys($set['scorers']['A']))->toBe(range(1, 31));
    expect($set['scorers']['B'])->toHaveCount(1);
});

test('the winner shown on a sheet is that set\'s winner and stays empty while the set is undecided', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);
    $game = $match->games()->first();
    $playerA = $match->sidePlayers('A')[0];

    $this->actingAs($umpire);
    expect($match->scoresheetSets()[0]['winner'])->toBeNull();

    foreach (range(3, 22) as $n) {
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $playerA['id'], 'rally_number' => $n]);
    }

    expect($match->fresh()->scoresheetSets()[0]['winner'])->toBe('A');
    $this->get(route('umpire.matches.scoresheet', $match))->assertSee('Team 1');
});

test('a long game ends on the winning team\'s last numbered cell instead of being cut off at rally 31', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);
    $match->games()->delete();

    $game = MatchGame::create(['match_id' => $match->id, 'game_number' => 1]);
    $playerA = $match->sidePlayers('A')[0];
    $playerB = $match->sidePlayers('B')[0];

    // 31-30 over 61 rallies, alternating, Team 1 taking the last point.
    foreach (range(1, 61) as $n) {
        $scorer = $n % 2 === 1 ? $playerA : $playerB;
        Rally::create(['match_game_id' => $game->id, 'scoring_player_id' => $scorer['id'], 'rally_number' => $n]);
    }

    $set = $match->fresh()->scoresheetSets()[0];

    expect($set['scorers']['A'])->toHaveKey(31)
        ->and($set['scorers']['B'])->toHaveKey(30)
        ->and($set['scorers']['B'])->not->toHaveKey(31);
});

test('the sheet of a walkover names the awarded team as the winner', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);
    $match->games()->delete();
    $match->update([
        'status' => 'completed',
        'result_type' => 'walkover',
        'winner_application_id' => $match->applicationForSide('B')->id,
    ]);

    $this->actingAs($umpire);

    $this->get(route('umpire.matches.scoresheet', $match))->assertSee('Team 2 (walkover)');
    $this->get(route('umpire.matches.scoresheet.download', $match))->assertSee('Team 2 (walkover)', false);
});
