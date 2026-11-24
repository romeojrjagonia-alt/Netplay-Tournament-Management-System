<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Illuminate\Database\Seeder;

/**
 * Local-testing data for statistic-based seeding. It creates a published
 * single-elimination tournament with an un-generated singles and doubles
 * draw. Entries are approved in the *opposite* order to their strength, so
 * the schedule page's default bracket should still put the strongest
 * players on top, the weakest against them, and the next seeds in the other
 * half. Run it explicitly (it is not part of DatabaseSeeder):
 *
 *   php artisan db:seed --class=StatisticSeedingDemoSeeder
 *
 * Running it again does nothing if the tournament already exists.
 */
class StatisticSeedingDemoSeeder extends Seeder
{
    public const TOURNAMENT = 'Statistic Seeding Demo Open';

    /**
     * Singles entries in approval order: [name, level at application, wins, losses].
     *
     * @var list<array{0: string, 1: string, 2: int, 3: int}>
     */
    private const SINGLES = [
        ['Wally Newbie', 'Beginner', 0, 0],
        ['Ian Intermediate', 'Intermediate', 3, 2],
        ['Gabe Grinder', 'Level D', 12, 12],
        ['Dante Steady', 'Level D', 6, 4],
        ['Dominic Dominant', 'Level D', 18, 2],
        ['Cesar Climber', 'Level C', 5, 5],
        ['Bryan Ranked', 'Level B', 9, 3],
        ['Alex Ace', 'Level A', 20, 4],
    ];

    /**
     * Doubles entries in approval order: two players each, [name, level, wins, losses].
     *
     * @var list<list<array{0: string, 1: string, 2: int, 3: int}>>
     */
    private const DOUBLES = [
        [['Nina Novice', 'Beginner', 1, 4], ['Nora Novice', 'Beginner', 0, 3]],
        [['Mia Mixed', 'Level A', 6, 4], ['Max Mixed', 'Beginner', 0, 0]],
        [['Dee Duo', 'Level D', 10, 2], ['Dan Duo', 'Level D', 8, 4]],
        [['Cleo Core', 'Level C', 7, 7], ['Cal Core', 'Level C', 9, 5]],
    ];

    public function run(): void
    {
        if (Tournament::where('name', self::TOURNAMENT)->exists()) {
            return;
        }

        $tournament = Tournament::create([
            'name' => self::TOURNAMENT,
            'format' => 'single_elimination',
            'sport' => 'Badminton',
            'location' => 'OBC Badminton Facility, Ozamiz City',
            'scoring_system' => 'Best of 3 to 21',
            'date_start' => now()->addDays(14)->toDateString(),
            'date_end' => now()->addDays(15)->toDateString(),
            'time' => '09:00',
            'registration_start' => now()->subDays(7)->toDateString(),
            'registration_end' => now()->addDays(7)->toDateString(),
            'status' => 'published',
        ]);

        $singles = $this->tournamentCategory($tournament, "Statistic Demo Men's Singles", 'male', 1);

        foreach (self::SINGLES as $player) {
            $this->entry($singles, [$player]);
        }

        $doubles = $this->tournamentCategory($tournament, 'Statistic Demo Mixed Doubles', 'mixed', 2);

        foreach (self::DOUBLES as $team) {
            $this->entry($doubles, $team);
        }
    }

    private function tournamentCategory(Tournament $tournament, string $name, string $gender, int $teamSize): TournamentCategory
    {
        $category = Category::firstOrCreate(
            ['name' => $name],
            ['gender' => $gender, 'team_size' => $teamSize],
        );

        return TournamentCategory::create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'registration_fee' => 300,
            'max_slots' => 16,
            'status' => 'open',
        ]);
    }

    /**
     * Add an approved entry made of the given players.
     *
     * @param  list<array{0: string, 1: string, 2: int, 3: int}>  $players
     */
    private function entry(TournamentCategory $tournamentCategory, array $players): void
    {
        $application = PlayerApplication::create([
            'tournament_category_id' => $tournamentCategory->id,
            'status' => 'approved',
        ]);

        foreach ($players as [$name, $level, $wins, $losses]) {
            $player = Player::firstOrCreate(['name' => $name], ['level' => $level]);

            PlayerStatistic::updateOrCreate(
                ['player_id' => $player->id],
                ['matches_played' => $wins + $losses, 'wins' => $wins, 'losses' => $losses],
            );

            ApplicationPlayer::create([
                'application_id' => $application->id,
                'player_id' => $player->id,
                'level_at_apply' => $level,
            ]);
        }
    }
}
