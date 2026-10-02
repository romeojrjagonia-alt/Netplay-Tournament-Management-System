<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScheduleDemoSeeder extends Seeder
{
    /**
     * Seed a published round-robin tournament with approved entries and no
     * generated schedule, for manually testing "Schedule Matches":
     *
     * - Men's Singles (Open): 5 entries — odd count, so one entry rests each round
     * - Men's Singles (Level B): 4 entries — even count; reroute destination
     *   (age 35 max, so the 41-year-old in Open warns on a move)
     * - Women's Singles: 2 entries — reroute destination with a different fee
     *   and gender (a male entry warns on a move)
     * - Men's Doubles: 2 pairs — team size 2, so singles entries can't be moved here
     *
     * Also creates an admin and an umpire to log in with (password: "password").
     * Safe to re-run: nothing is duplicated.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'schedule-admin@example.com'], [
            'name' => 'Schedule Admin',
            'password' => 'password',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        User::firstOrCreate(['email' => 'schedule-umpire@example.com'], [
            'name' => 'Schedule Umpire',
            'password' => 'password',
            'role' => 'umpire',
            'email_verified_at' => now(),
        ]);

        $tournament = Tournament::firstOrCreate(['name' => 'Schedule Demo Open'], [
            'format' => 'round_robin',
            'sport' => 'Badminton',
            'location' => 'OBC Badminton Facility, Ozamiz City',
            'scoring_system' => 'Best of 3 to 21',
            'date_start' => '2026-10-17',
            'date_end' => '2026-10-18',
            'time' => '9:00 AM - 5:00 PM',
            'specific_days' => 'Sat, Sun',
            'registration_start' => '2026-09-15',
            'registration_end' => '2026-10-10',
            'status' => 'published',
            'tiebreaker_rules' => ['head_to_head', 'point_differential'],
        ]);

        if ($tournament->tournamentCategories()->exists()) {
            return;
        }

        $openSingles = $this->category($tournament, "Men's Singles", 'male', 1, 'Open', null, null, 250);
        $levelBSingles = $this->category($tournament, "Men's Singles", 'male', 1, 'Level B', 18, 35, 300);
        $womensSingles = $this->category($tournament, "Women's Singles", 'female', 1, 'Open', null, null, 200);
        $mensDoubles = $this->category($tournament, "Men's Doubles", 'male', 2, 'Open', null, null, 400);

        $openPlayers = [
            ['Marcus Villanueva', '1998-03-22'],
            ['Ramon Dela Cruz', '1995-11-02'],
            ['Paolo Santos', '1996-02-14'],
            ['Miguel Torres', '1994-08-30'],
            ['Ramil Cabrera', '1985-01-12'],
        ];

        foreach ($openPlayers as [$name, $birthDate]) {
            $this->approvedEntry($openSingles, [$this->player($name, $birthDate, 'male')]);
        }

        foreach ([['Jerome Alcala', '2000-06-05'], ['Kevin Lumapas', '1999-09-19'], ['Dennis Ybanez', '2001-04-27'], ['Carlo Mendoza', '2002-12-08']] as [$name, $birthDate]) {
            $this->approvedEntry($levelBSingles, [$this->player($name, $birthDate, 'male')]);
        }

        foreach ([['Ella Bautista', '2001-07-09'], ['Cristina Reyes', '1997-05-18']] as [$name, $birthDate]) {
            $this->approvedEntry($womensSingles, [$this->player($name, $birthDate, 'female')]);
        }

        $this->approvedEntry($mensDoubles, [
            $this->player('Rey Gomez', '1993-03-03', 'male'),
            $this->player('Allan Pacana', '1992-10-10', 'male'),
        ]);
        $this->approvedEntry($mensDoubles, [
            $this->player('Joel Navarro', '1991-07-21', 'male'),
            $this->player('Bong Tan', '1990-02-17', 'male'),
        ]);
    }

    private function category(Tournament $tournament, string $name, string $gender, int $teamSize, string $level, ?int $ageMin, ?int $ageMax, int $fee): TournamentCategory
    {
        $category = Category::firstOrCreate(
            ['name' => $name, 'gender' => $gender, 'team_size' => $teamSize, 'level' => $level],
            ['age_min' => $ageMin, 'age_max' => $ageMax]
        );

        return TournamentCategory::create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'registration_fee' => $fee,
            'max_slots' => 16,
            'status' => 'open',
        ]);
    }

    private function player(string $name, string $birthDate, string $gender): Player
    {
        return Player::firstOrCreate(['name' => $name], [
            'birth_date' => $birthDate,
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
