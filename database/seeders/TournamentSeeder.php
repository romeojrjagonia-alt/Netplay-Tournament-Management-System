<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    /**
     * Seed a handful of demo tournaments so the player Events page has
     * something to show before the admin tournament-creation UI exists.
     */
    public function run(): void
    {
        $mensSingles = Category::firstOrCreate(
            ['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1],
            ['level' => 'Open']
        );

        $womensSingles = Category::firstOrCreate(
            ['name' => "Women's Singles", 'gender' => 'female', 'team_size' => 1],
            ['level' => 'Open']
        );

        $mixedDoubles = Category::firstOrCreate(
            ['name' => 'Mixed Doubles', 'gender' => 'mixed', 'team_size' => 2],
            ['level' => 'Open']
        );

        $inato = Tournament::firstOrCreate(
            ['name' => 'Inato Tournament'],
            [
                'format' => 'single_elimination',
                'sport' => 'Badminton',
                'scoring_system' => 'Best of 3 to 21',
                'date_start' => '2026-07-01',
                'date_end' => '2026-07-31',
                'time' => '9:00 AM - 5:00 PM',
                'specific_days' => 'SAT, SUN',
                'registration_start' => '2026-06-01',
                'registration_end' => '2026-08-12',
                'status' => 'registration_open',
            ]
        );

        $barangayCup = Tournament::firstOrCreate(
            ['name' => 'Barangay Cup'],
            [
                'format' => 'round_robin',
                'sport' => 'Badminton',
                'scoring_system' => 'Best of 3 to 21',
                'date_start' => '2026-09-05',
                'date_end' => '2026-09-20',
                'time' => '1:00 PM - 6:00 PM',
                'specific_days' => 'SAT, SUN',
                'registration_start' => '2026-07-01',
                'registration_end' => '2026-09-01',
                'status' => 'registration_open',
            ]
        );

        Tournament::firstOrCreate(
            ['name' => 'Summer Slam'],
            [
                'format' => 'double_elimination',
                'sport' => 'Badminton',
                'scoring_system' => 'Best of 3 to 21',
                'date_start' => '2026-12-01',
                'date_end' => '2026-12-10',
                'time' => '9:00 AM - 5:00 PM',
                'specific_days' => 'SAT, SUN',
                'registration_start' => '2026-11-01',
                'registration_end' => '2026-11-25',
                'status' => 'upcoming',
            ]
        );

        $inatoSingles = TournamentCategory::firstOrCreate(
            ['tournament_id' => $inato->id, 'category_id' => $mensSingles->id],
            ['registration_fee' => 250, 'max_slots' => 32, 'status' => 'open']
        );

        $inatoMixedDoubles = TournamentCategory::firstOrCreate(
            ['tournament_id' => $inato->id, 'category_id' => $mixedDoubles->id],
            ['registration_fee' => 400, 'max_slots' => 16, 'status' => 'open']
        );

        $barangayWomensSingles = TournamentCategory::firstOrCreate(
            ['tournament_id' => $barangayCup->id, 'category_id' => $womensSingles->id],
            ['registration_fee' => 200, 'max_slots' => 24, 'status' => 'open']
        );

        $player = Player::first();

        if ($player) {
            $pendingApplication = PlayerApplication::firstOrCreate(
                ['tournament_category_id' => $inatoSingles->id],
                ['status' => 'pending_verification']
            );

            ApplicationPlayer::firstOrCreate([
                'application_id' => $pendingApplication->id,
                'player_id' => $player->id,
            ], [
                'level_at_apply' => $player->level ?? 'Beginner',
            ]);

            $paymentPendingApplication = PlayerApplication::firstOrCreate(
                ['tournament_category_id' => $barangayWomensSingles->id],
                ['status' => 'payment_pending']
            );

            ApplicationPlayer::firstOrCreate([
                'application_id' => $paymentPendingApplication->id,
                'player_id' => $player->id,
            ], [
                'level_at_apply' => $player->level ?? 'Beginner',
            ]);

            $this->seedDemoMatches($player, $inatoMixedDoubles);
        }
    }

    /**
     * Seed a small mixed-doubles rivalry (one entry for the demo player, one
     * for a recurring opponent pair) with a scheduled, in-progress, and two
     * completed matches, so the player "My Matches" page has something to show.
     */
    private function seedDemoMatches(Player $player, TournamentCategory $tournamentCategory): void
    {
        if (Matches::where('tournament_category_id', $tournamentCategory->id)->exists()) {
            return;
        }

        $partner = Player::firstOrCreate(['name' => 'Juan Torres'], ['level' => $player->level ?? 'Beginner']);
        $opponentOne = Player::firstOrCreate(['name' => 'Mark Santos'], ['level' => 'Beginner']);
        $opponentTwo = Player::firstOrCreate(['name' => 'Ana Reyes'], ['level' => 'Beginner']);

        $playerTeamApplication = PlayerApplication::create([
            'tournament_category_id' => $tournamentCategory->id,
            'status' => 'approved',
        ]);

        foreach ([$player, $partner] as $teamPlayer) {
            ApplicationPlayer::create([
                'application_id' => $playerTeamApplication->id,
                'player_id' => $teamPlayer->id,
                'level_at_apply' => $teamPlayer->level ?? 'Beginner',
            ]);
        }

        $opponentTeamApplication = PlayerApplication::create([
            'tournament_category_id' => $tournamentCategory->id,
            'status' => 'approved',
        ]);

        foreach ([$opponentOne, $opponentTwo] as $teamPlayer) {
            ApplicationPlayer::create([
                'application_id' => $opponentTeamApplication->id,
                'player_id' => $teamPlayer->id,
                'level_at_apply' => $teamPlayer->level ?? 'Beginner',
            ]);
        }

        // Created as 'scheduled' and only marked 'completed' once both
        // entries and games are attached, so MatchObserver — which reacts to
        // the status transition — credits the right players' statistics.
        $wonMatch = Matches::create([
            'tournament_category_id' => $tournamentCategory->id,
            'bracket_type' => 'single_elimination',
            'round' => 1,
            'position' => 1,
            'status' => 'scheduled',
            'scheduled_at' => now()->subDays(14),
            'court' => 'Court 3',
        ]);

        MatchApplication::create(['match_id' => $wonMatch->id, 'application_id' => $playerTeamApplication->id]);
        MatchApplication::create(['match_id' => $wonMatch->id, 'application_id' => $opponentTeamApplication->id]);

        MatchGame::create(['match_id' => $wonMatch->id, 'game_number' => 1, 'winner_application_id' => $playerTeamApplication->id]);
        MatchGame::create(['match_id' => $wonMatch->id, 'game_number' => 2, 'winner_application_id' => $opponentTeamApplication->id]);
        MatchGame::create(['match_id' => $wonMatch->id, 'game_number' => 3, 'winner_application_id' => $playerTeamApplication->id]);

        $wonMatch->update(['status' => 'completed', 'winner_application_id' => $playerTeamApplication->id]);

        $lostMatch = Matches::create([
            'tournament_category_id' => $tournamentCategory->id,
            'bracket_type' => 'single_elimination',
            'round' => 2,
            'position' => 1,
            'status' => 'scheduled',
            'scheduled_at' => now()->subDays(7),
            'court' => 'Court 2',
        ]);

        MatchApplication::create(['match_id' => $lostMatch->id, 'application_id' => $playerTeamApplication->id]);
        MatchApplication::create(['match_id' => $lostMatch->id, 'application_id' => $opponentTeamApplication->id]);

        MatchGame::create(['match_id' => $lostMatch->id, 'game_number' => 1, 'winner_application_id' => $opponentTeamApplication->id]);
        MatchGame::create(['match_id' => $lostMatch->id, 'game_number' => 2, 'winner_application_id' => $opponentTeamApplication->id]);

        $lostMatch->update(['status' => 'completed', 'winner_application_id' => $opponentTeamApplication->id]);

        $ongoingMatch = Matches::create([
            'tournament_category_id' => $tournamentCategory->id,
            'bracket_type' => 'single_elimination',
            'round' => 3,
            'position' => 1,
            'status' => 'in_progress',
            'scheduled_at' => now()->subHour(),
            'court' => 'Court 2',
        ]);

        MatchApplication::create(['match_id' => $ongoingMatch->id, 'application_id' => $playerTeamApplication->id]);
        MatchApplication::create(['match_id' => $ongoingMatch->id, 'application_id' => $opponentTeamApplication->id]);

        $upcomingMatch = Matches::create([
            'tournament_category_id' => $tournamentCategory->id,
            'bracket_type' => 'single_elimination',
            'round' => 4,
            'position' => 1,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(5)->setTime(9, 0),
            'court' => 'Court 1',
        ]);

        MatchApplication::create(['match_id' => $upcomingMatch->id, 'application_id' => $playerTeamApplication->id]);
        MatchApplication::create(['match_id' => $upcomingMatch->id, 'application_id' => $opponentTeamApplication->id]);
    }
}
