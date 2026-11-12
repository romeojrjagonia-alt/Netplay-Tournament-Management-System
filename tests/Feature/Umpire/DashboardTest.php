<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function umpireDashboardTournamentCategory(array $tournamentOverrides = []): TournamentCategory
{
    $tournament = Tournament::create(array_merge([
        'name' => 'Inato Tournament',
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
    ], $tournamentOverrides));

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

function umpireDashboardApplicationFor(TournamentCategory $tournamentCategory, Player $player): PlayerApplication
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

function umpireDashboardMatch(TournamentCategory $tournamentCategory, array $overrides = []): Matches
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

test('a non-umpire cannot view the dashboard', function () {
    $user = User::factory()->create(['role' => 'player']);

    $this->actingAs($user);

    Volt::test('umpire.dashboard')->assertForbidden();
});

test('the dashboard only counts and lists matches assigned to the current umpire', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $otherUmpire = User::factory()->create(['role' => 'umpire']);

    $tournamentCategory = umpireDashboardTournamentCategory();
    $applicationA = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Mark Santos']));
    $applicationB = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Juan Torres']));

    $myMatch = umpireDashboardMatch($tournamentCategory, [
        'assigned_umpire_id' => $umpire->id,
        'court' => 'Court 2',
        'scheduled_at' => now()->addDay(),
    ]);
    MatchApplication::create(['match_id' => $myMatch->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $myMatch->id, 'application_id' => $applicationB->id]);

    $otherMatch = umpireDashboardMatch($tournamentCategory, [
        'assigned_umpire_id' => $otherUmpire->id,
        'court' => 'Court 9',
    ]);
    MatchApplication::create(['match_id' => $otherMatch->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $otherMatch->id, 'application_id' => $applicationB->id]);

    $this->actingAs($umpire);

    Volt::test('umpire.dashboard')
        ->assertSee('Inato Tournament')
        ->assertSee('Court 2')
        ->assertDontSee('Court 9')
        ->assertSet('assignedMatchesCount', 1)
        ->assertSet('accomplishedMatchesCount', 0);
});

test('the previous match result shows the winning team for a completed assigned match', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $tournamentCategory = umpireDashboardTournamentCategory();
    $applicationA = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Mark Santos']));
    $applicationB = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Juan Torres']));

    $match = umpireDashboardMatch($tournamentCategory, [
        'assigned_umpire_id' => $umpire->id,
        'status' => 'completed',
        'winner_application_id' => $applicationA->id,
        'scheduled_at' => now()->subDay(),
    ]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationA->id]);
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationB->id]);

    $this->actingAs($umpire);

    Volt::test('umpire.dashboard')
        ->assertSee('Winner')
        ->assertSee('Team A')
        ->assertSet('accomplishedMatchesCount', 1);
});

function umpireDashboardPairedMatch(User $umpire, array $overrides = [], bool $withBothTeams = true): Matches
{
    $tournamentCategory = umpireDashboardTournamentCategory();
    $applicationA = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Mark Santos']));
    $match = umpireDashboardMatch($tournamentCategory, array_merge(['assigned_umpire_id' => $umpire->id], $overrides));
    MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationA->id]);

    if ($withBothTeams) {
        $applicationB = umpireDashboardApplicationFor($tournamentCategory, Player::create(['name' => 'Juan Torres']));
        MatchApplication::create(['match_id' => $match->id, 'application_id' => $applicationB->id]);
    }

    return $match;
}

test('upcoming match cards open the match details modal', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = umpireDashboardPairedMatch($umpire);

    $this->actingAs($umpire);

    Volt::test('umpire.dashboard')
        ->assertSeeHtml('$dispatch(\'open-match-details\', { matchId: '.$match->id.' })');
});

test('the match details modal shows the match and a proceed to scoring button', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = umpireDashboardPairedMatch($umpire, ['court' => 'Court 4', 'round' => 2]);

    $this->actingAs($umpire);

    Volt::test('umpire.match-details')
        ->dispatch('open-match-details', matchId: $match->id)
        ->assertSee('Match Details')
        ->assertSee('Inato Tournament')
        ->assertSee('Court 4')
        ->assertSee('Round 2')
        ->assertSee('Mark Santos')
        ->assertSee('Juan Torres')
        ->assertSee('Proceed to scoring')
        ->assertSee(route('umpire.matches.scoring', $match), false);
});

test('an in-progress match offers to continue scoring', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = umpireDashboardPairedMatch($umpire, ['status' => 'in_progress']);

    $this->actingAs($umpire);

    Volt::test('umpire.match-details')
        ->dispatch('open-match-details', matchId: $match->id)
        ->assertSee('Continue to scoring');
});

test('scoring cannot be started from the details modal until both teams are known', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = umpireDashboardPairedMatch($umpire, withBothTeams: false);

    $this->actingAs($umpire);

    Volt::test('umpire.match-details')
        ->dispatch('open-match-details', matchId: $match->id)
        ->assertSee('Waiting for both teams')
        ->assertDontSee('Proceed to scoring')
        ->assertDontSee(route('umpire.matches.scoring', $match), false);
});

test("the details modal does not open another umpire's or a finished match", function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $other = User::factory()->create(['role' => 'umpire']);
    $othersMatch = umpireDashboardPairedMatch($other, ['court' => 'Court 9']);
    $finished = umpireDashboardPairedMatch($umpire, ['status' => 'completed', 'court' => 'Court 8']);

    $this->actingAs($umpire);

    Volt::test('umpire.match-details')
        ->dispatch('open-match-details', matchId: $othersMatch->id)
        ->assertSee('Match details are not available.')
        ->assertDontSee('Court 9')
        ->dispatch('open-match-details', matchId: $finished->id)
        ->assertSee('Match details are not available.')
        ->assertDontSee('Court 8');
});

test('the match details modal shows a loader until the match arrives, so a previous match never flashes', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = umpireDashboardPairedMatch($umpire);

    $this->actingAs($umpire);

    Volt::test('umpire.match-details')
        ->assertSee('Loading match details')
        ->assertSeeHtml('x-show="loading"')
        ->assertSeeHtml('x-show="! loading"')
        ->dispatch('open-match-details', matchId: $match->id)
        ->assertDispatched('match-details-loaded');
});

test('the match summary modal shows a loader until the match arrives', function () {
    $umpire = User::factory()->create(['role' => 'umpire']);
    $match = umpireDashboardPairedMatch($umpire, ['status' => 'completed']);

    $this->actingAs($umpire);

    Volt::test('umpire.match-summary')
        ->assertSee('Loading match summary')
        ->dispatch('open-match-summary', matchId: $match->id)
        ->assertDispatched('match-summary-loaded');
});
