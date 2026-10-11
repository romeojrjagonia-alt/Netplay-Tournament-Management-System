<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Scheduling\SingleEliminationScheduler;
use Illuminate\Database\Seeder;

class BracketDemoSeeder extends Seeder
{
    /**
     * Seed a published single-elimination tournament for manually testing the
     * bracket editor and the bracket display:
     *
     * - Men's Singles (Open): 6 entries, no schedule yet. Arrange the
     *   pairings here; 6 entries in an 8-slot bracket means two BYEs.
     * - Men's Doubles (Open): 4 pairs, no schedule yet. Entries show both
     *   partners' names.
     * - Women's Singles (Open): 4 entries, already generated, with the first
     *   semifinal played, to see the read-only bracket with a score.
     *
     * Uses the admin from ScheduleDemoSeeder (schedule-admin@example.com,
     * password: "password"), creating it if missing. Safe to re-run.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'schedule-admin@example.com'], [
            'name' => 'Schedule Admin',
            'password' => 'password',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $tournament = Tournament::firstOrCreate(['name' => 'Bracket Demo Open'], [
            'format' => 'single_elimination',
            'sport' => 'Badminton',
            'location' => 'OBC Badminton Facility, Ozamiz City',
            'scoring_system' => 'Best of 3 to 21',
            'date_start' => '2026-10-24',
            'date_end' => '2026-10-25',
            'time' => '9:00 AM - 5:00 PM',
            'specific_days' => 'Sat, Sun',
            'registration_start' => '2026-10-01',
            'registration_end' => '2026-10-20',
            'status' => 'published',
        ]);

        if ($tournament->tournamentCategories()->exists()) {
            return;
        }

        $mensSingles = $this->category($tournament, "Men's Singles", 'male', 1, 250);
        $mensDoubles = $this->category($tournament, "Men's Doubles", 'male', 2, 400);
        $womensSingles = $this->category($tournament, "Women's Singles", 'female', 1, 200);

        foreach (['Marcus Villanueva', 'Ramon Dela Cruz', 'Paolo Santos', 'Miguel Torres', 'Ramil Cabrera', 'Jerome Alcala'] as $name) {
            $this->approvedEntry($mensSingles, [$this->player($name, 'male')]);
        }

        foreach ([['Rey Gomez', 'Allan Pacana'], ['Joel Navarro', 'Bong Tan'], ['Kevin Lumapas', 'Dennis Ybanez'], ['Carlo Mendoza', 'Nico Abella']] as [$first, $second]) {
            $this->approvedEntry($mensDoubles, [$this->player($first, 'male'), $this->player($second, 'male')]);
        }

        foreach (['Ella Bautista', 'Cristina Reyes', 'Maria Lopez', 'Sofia Garcia'] as $name) {
            $this->approvedEntry($womensSingles, [$this->player($name, 'female')]);
        }

        $this->playFirstMatch($womensSingles);
    }

    /**
     * Generate the category's bracket and finish its first match 2-0.
     */
    private function playFirstMatch(TournamentCategory $tournamentCategory): void
    {
        app(SingleEliminationScheduler::class)->generate($tournamentCategory);

        $match = Matches::where('tournament_category_id', $tournamentCategory->id)
            ->where('round', 1)
            ->where('position', 1)
            ->firstOrFail();

        $winner = $match->applicationForSide('A');

        foreach ([1, 2] as $gameNumber) {
            MatchGame::create([
                'match_id' => $match->id,
                'game_number' => $gameNumber,
                'winner_application_id' => $winner->id,
            ]);
        }

        $match->update(['status' => 'completed', 'winner_application_id' => $winner->id]);
    }

    private function category(Tournament $tournament, string $name, string $gender, int $teamSize, int $fee): TournamentCategory
    {
        $category = Category::firstOrCreate(
            ['name' => $name, 'gender' => $gender, 'team_size' => $teamSize, 'level' => 'Open'],
        );

        return TournamentCategory::create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'registration_fee' => $fee,
            'max_slots' => 16,
            'status' => 'open',
        ]);
    }

    private function player(string $name, string $gender): Player
    {
        return Player::firstOrCreate(['name' => $name], [
            'birth_date' => '1996-01-01',
            'gender' => $gender,
            'address' => 'Ozamiz City',
            'level' => 'Intermediate',
        ]);
    }

    /**
     * @param  array<int, Player>  $players
     */
    private function approvedEntry(TournamentCategory $tournamentCategory, array $players): void
    {
        $application = PlayerApplication::create([
            'tournament_category_id' => $tournamentCategory->id,
            'status' => 'approved',
        ]);

        foreach ($players as $player) {
            ApplicationPlayer::create([
                'application_id' => $application->id,
                'player_id' => $player->id,
                'level_at_apply' => $player->level,
            ]);
        }
    }
}
