<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Scheduling\RoundRobinScheduler;
use App\Support\Scheduling\SingleEliminationScheduler;
use App\Support\Scheduling\TimetableOptions;
use Illuminate\Database\Seeder;

class RegenerateDemoSeeder extends Seeder
{
    /**
     * Seed two published events for manually testing schedule regeneration
     * and automatic dating. Dates are relative to today, so slots are always
     * in the future. Safe to re-run.
     *
     * "Round Robin Regen Demo" (Sat/Sun, 9 AM - 5 PM, next 3 weeks):
     * - Men's Singles: 4 entries, generated AND auto-dated, then 2 more
     *   approved -> shows "Entries changed" and Regenerate.
     * - Women's Singles: 4 entries, generated and auto-dated, then one
     *   rejected -> shows a removed entry.
     * - Mixed Doubles: 5 pairs, not generated -> try auto-dating yourself
     *   (try 1 court / 120 min to trigger the "doesn't fit" refusal).
     * - Men's Doubles: 3 pairs with a started match -> regeneration blocked.
     *
     * "Single Elim Regen Demo": Men's Singles with 4 entries generated and
     * auto-dated, then 1 more approved (arrangement kept after Regenerate).
     *
     * Login: schedule-admin@example.com / password.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'schedule-admin@example.com'], [
            'name' => 'Schedule Admin',
            'password' => 'password',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        if (Tournament::where('name', 'Round Robin Regen Demo')->exists()) {
            return;
        }

        $this->roundRobinEvent();
        $this->singleEliminationEvent();
    }

    private function roundRobinEvent(): void
    {
        $tournament = $this->tournament('Round Robin Regen Demo', 'round_robin');
        $options = new TimetableOptions(40, 5, 2);
        $robin = app(RoundRobinScheduler::class);

        $mens = $this->category($tournament, "Men's Singles", 'male', 1);
        $this->entries($mens, ['Alvin Reyes', 'Bryan Cruz', 'Carlo Diaz', 'Dante Lim'], 'male');
        $robin->generate($mens, $options);
        $this->entries($mens, ['Edgar Soto', 'Felix Tan'], 'male');

        $womens = $this->category($tournament, "Women's Singles", 'female', 1);
        $this->entries($womens, ['Gina Abad', 'Hazel Bay', 'Irene Co', 'Joy Dela Pena'], 'female');
        $robin->generate($womens, $options);
        $womens->playerApplications()->latest('id')->first()->update(['status' => 'rejected']);

        $mixed = $this->category($tournament, 'Mixed Doubles', 'mixed', 2);

        foreach ([['Ken Uy', 'Lara Go'], ['Marc Ong', 'Nina Lao'], ['Omar Yu', 'Pia Sy'], ['Quin Ang', 'Rita Chua'], ['Sam Tiu', 'Tess Lee']] as $pair) {
            $this->entry($mixed, [$this->player($pair[0], 'male'), $this->player($pair[1], 'female')]);
        }

        $doubles = $this->category($tournament, "Men's Doubles", 'male', 2);

        foreach ([['Uri Vega', 'Vic Wong'], ['Walt Xu', 'Yan Zeng'], ['Zed Aban', 'Abe Boo']] as $pair) {
            $this->entry($doubles, [$this->player($pair[0], 'male'), $this->player($pair[1], 'male')]);
        }

        $robin->generate($doubles, $options);
        $doubles->matches()->orderBy('round')->orderBy('position')->first()->update(['status' => 'in_progress']);
    }

    private function singleEliminationEvent(): void
    {
        $tournament = $this->tournament('Single Elim Regen Demo', 'single_elimination');

        $category = $this->category($tournament, "Men's Singles", 'male', 1);
        $this->entries($category, ['Ace Lopez', 'Ben Mar', 'Cal Nunez', 'Dex Ortiz'], 'male');
        $scheduler = app(SingleEliminationScheduler::class);
        $scheduler->swap($category, 0, 3);
        $scheduler->generate($category, new TimetableOptions(40, 5, 2));
        $this->entries($category, ['Eli Perez'], 'male');
    }

    private function tournament(string $name, string $format): Tournament
    {
        $start = now()->next('Saturday');

        return Tournament::create([
            'name' => $name,
            'format' => $format,
            'sport' => 'Badminton',
            'location' => 'OBC Badminton Facility, Ozamiz City',
            'scoring_system' => 'Best of 3 to 21',
            'date_start' => $start->toDateString(),
            'date_end' => $start->copy()->addWeeks(3)->toDateString(),
            'time' => '9:00 AM - 5:00 PM',
            'specific_days' => 'Sun, Sat',
            'registration_start' => now()->subWeek()->toDateString(),
            'registration_end' => $start->copy()->subDay()->toDateString(),
            'status' => 'published',
        ]);
    }

    private function category(Tournament $tournament, string $name, string $gender, int $teamSize): TournamentCategory
    {
        $category = Category::firstOrCreate(['name' => $name, 'gender' => $gender, 'team_size' => $teamSize, 'level' => 'Open']);

        return TournamentCategory::create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'registration_fee' => 250,
            'max_slots' => 16,
            'status' => 'open',
        ]);
    }

    /**
     * @param  list<string>  $names
     */
    private function entries(TournamentCategory $tournamentCategory, array $names, string $gender): void
    {
        foreach ($names as $name) {
            $this->entry($tournamentCategory, [$this->player($name, $gender)]);
        }
    }

    /**
     * @param  list<Player>  $players
     */
    private function entry(TournamentCategory $tournamentCategory, array $players): void
    {
        $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);

        foreach ($players as $player) {
            ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => $player->level]);
        }
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
}
