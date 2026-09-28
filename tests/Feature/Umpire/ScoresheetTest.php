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

    $this->get(route('umpire.matches.scoresheet', $match))->assertForbidden();
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
        ->assertSee(['GAME NO', 'COURT', 'BRACKET', 'CATEGORY', 'TIME'])
        ->assertSee(['SET 1', 'SET 2'])
        ->assertSee(['UMPIRE:', 'Signature', 'WINNER:'], false);
});

test('the downloaded document uses the same layout with a landscape page and filled score cells', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    $this->actingAs($umpire);

    $response = $this->get(route('umpire.matches.scoresheet.download', $match))
        ->assertOk()
        ->assertSee('INATO TOURNAMENT', false)
        ->assertSee(['GAME NO', 'COURT', 'BRACKET', 'CATEGORY', 'TIME', 'UMPIRE:', 'Signature', 'WINNER:'], false)
        ->assertSee('mso-page-orientation: landscape', false);

    // One point each so far: a single filled cell per side.
    expect(substr_count($response->getContent(), 'bgcolor="#000000"'))->toBe(2);
});

test('the score sheet always lays out at least two sets and reports each side\'s score', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    $sets = $match->scoresheetSets();

    expect($sets)->toHaveCount(2);
    expect($sets[0]['scores'])->toBe(['A' => 1, 'B' => 1]);
    expect($sets[0]['winner'])->toBeNull();
    expect($sets[1]['scores'])->toBe(['A' => 0, 'B' => 0]);
});

test('the winning side comes from the confirmed result, and is empty while undecided', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = scoresheetMatch($umpire);

    expect($match->winnerSide())->toBeNull();

    $match->update([
        'status' => 'completed',
        'winner_application_id' => $match->matchApplications()->orderBy('id')->first()->application_id,
    ]);

    expect($match->fresh()->winnerSide())->toBe('A');
});
