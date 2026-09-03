<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;

function observerTournamentCategory(): TournamentCategory
{
    $tournament = Tournament::create([
        'name' => 'Observer Test Tournament',
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

function observerApplicationFor(TournamentCategory $tournamentCategory, Player $player): PlayerApplication
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

test('marking a match completed credits a win and a loss to the right players', function () {
    $tournamentCategory = observerTournamentCategory();
    $winner = Player::create(['name' => 'Winner Player']);
    $loser = Player::create(['name' => 'Loser Player']);

    $winnerApplication = observerApplicationFor($tournamentCategory, $winner);
    $loserApplication = observerApplicationFor($tournamentCategory, $loser);

    $match = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'court' => 'Court 1',
    ]);

    MatchApplication::create(['match_id' => $match->id, 'application_id' => $winnerApplication->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $loserApplication->id]);

    $match->update(['status' => 'completed', 'winner_application_id' => $winnerApplication->id]);

    expect($winner->statistic->fresh())
        ->matches_played->toBe(1)
        ->wins->toBe(1)
        ->losses->toBe(0);

    expect($loser->statistic->fresh())
        ->matches_played->toBe(1)
        ->wins->toBe(0)
        ->losses->toBe(1);
});

test('reverting a completed match back to scheduled reverses the credited statistics', function () {
    $tournamentCategory = observerTournamentCategory();
    $winner = Player::create(['name' => 'Winner Player']);
    $loser = Player::create(['name' => 'Loser Player']);

    $winnerApplication = observerApplicationFor($tournamentCategory, $winner);
    $loserApplication = observerApplicationFor($tournamentCategory, $loser);

    $match = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'court' => 'Court 1',
    ]);

    MatchApplication::create(['match_id' => $match->id, 'application_id' => $winnerApplication->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $loserApplication->id]);

    $match->update(['status' => 'completed', 'winner_application_id' => $winnerApplication->id]);

    $match->update(['status' => 'scheduled', 'winner_application_id' => null]);

    expect($winner->statistic->fresh())
        ->matches_played->toBe(0)
        ->wins->toBe(0);

    expect($loser->statistic->fresh())
        ->matches_played->toBe(0)
        ->losses->toBe(0);
});

test('correcting the winner of a completed match moves the win to the right player', function () {
    $tournamentCategory = observerTournamentCategory();
    $playerA = Player::create(['name' => 'Player A']);
    $playerB = Player::create(['name' => 'Player B']);

    $applicationA = observerApplicationFor($tournamentCategory, $playerA);
    $applicationB = observerApplicationFor($tournamentCategory, $playerB);

    $match = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'court' => 'Court 1',
    ]);

    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationB->id]);

    $match->update(['status' => 'completed', 'winner_application_id' => $applicationA->id]);
    $match->update(['winner_application_id' => $applicationB->id]);

    expect($playerA->statistic->fresh())
        ->matches_played->toBe(1)
        ->wins->toBe(0)
        ->losses->toBe(1);

    expect($playerB->statistic->fresh())
        ->matches_played->toBe(1)
        ->wins->toBe(1)
        ->losses->toBe(0);
});

test('winning the championship match promotes an unranked winner to Beginner', function () {
    $tournamentCategory = observerTournamentCategory();
    $winner = Player::create(['name' => 'Winner Player']);
    $loser = Player::create(['name' => 'Loser Player']);

    $winnerApplication = observerApplicationFor($tournamentCategory, $winner);
    $loserApplication = observerApplicationFor($tournamentCategory, $loser);

    // The only match in this category's bracket, so its round is the final.
    $championshipMatch = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'court' => 'Court 1',
    ]);

    MatchApplication::create(['match_id' => $championshipMatch->id, 'application_id' => $winnerApplication->id]);
    MatchApplication::create(['match_id' => $championshipMatch->id, 'application_id' => $loserApplication->id]);

    $championshipMatch->update(['status' => 'completed', 'winner_application_id' => $winnerApplication->id]);

    expect($winner->fresh()->level)->toBe('Beginner');
    expect($loser->fresh()->level)->toBeNull();
});

test('winning a non-final round does not promote the winner', function () {
    $tournamentCategory = observerTournamentCategory();
    $winner = Player::create(['name' => 'Winner Player']);
    $loser = Player::create(['name' => 'Loser Player']);
    $finalist = Player::create(['name' => 'Finalist Player']);

    $winnerApplication = observerApplicationFor($tournamentCategory, $winner);
    $loserApplication = observerApplicationFor($tournamentCategory, $loser);
    $finalistApplication = observerApplicationFor($tournamentCategory, $finalist);

    $semifinal = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'court' => 'Court 1',
    ]);

    // A round 2 match exists in the same category, so round 1 is not final.
    Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 2,
        'position' => 1,
        'status' => 'scheduled',
        'court' => 'Court 1',
    ]);

    MatchApplication::create(['match_id' => $semifinal->id, 'application_id' => $winnerApplication->id]);
    MatchApplication::create(['match_id' => $semifinal->id, 'application_id' => $loserApplication->id]);

    $semifinal->update(['status' => 'completed', 'winner_application_id' => $winnerApplication->id]);

    expect($winner->fresh()->level)->toBeNull();
});

test('a second championship win advances a Beginner to Intermediate', function () {
    $player = Player::create(['name' => 'Repeat Champion', 'level' => 'Beginner']);
    $opponent = Player::create(['name' => 'Opponent Player']);

    $tournamentCategory = observerTournamentCategory();
    $playerApplication = observerApplicationFor($tournamentCategory, $player);
    $opponentApplication = observerApplicationFor($tournamentCategory, $opponent);

    $championshipMatch = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'court' => 'Court 1',
    ]);

    MatchApplication::create(['match_id' => $championshipMatch->id, 'application_id' => $playerApplication->id]);
    MatchApplication::create(['match_id' => $championshipMatch->id, 'application_id' => $opponentApplication->id]);

    $championshipMatch->update(['status' => 'completed', 'winner_application_id' => $playerApplication->id]);

    expect($player->fresh()->level)->toBe('Intermediate');
});

test('a player already at the top of the ladder stays at Level A', function () {
    $player = Player::create(['name' => 'Top Player', 'level' => 'Level A']);
    $opponent = Player::create(['name' => 'Opponent Player']);

    $tournamentCategory = observerTournamentCategory();
    $playerApplication = observerApplicationFor($tournamentCategory, $player);
    $opponentApplication = observerApplicationFor($tournamentCategory, $opponent);

    $championshipMatch = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
        'court' => 'Court 1',
    ]);

    MatchApplication::create(['match_id' => $championshipMatch->id, 'application_id' => $playerApplication->id]);
    MatchApplication::create(['match_id' => $championshipMatch->id, 'application_id' => $opponentApplication->id]);

    $championshipMatch->update(['status' => 'completed', 'winner_application_id' => $playerApplication->id]);

    expect($player->fresh()->level)->toBe('Level A');
});
