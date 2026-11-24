<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\MatchGame;
use App\Models\Matches;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerStatistic;
use App\Models\Rally;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Scheduling\SingleEliminationScheduler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demonstration data covering the whole workflow. Run it explicitly (it is
 * not part of DatabaseSeeder, so production is never filled with fake data):
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * It creates:
 *  - an admin, two umpires and one player account (password: "password"
 *    for all but the admin, see AdminSeeder);
 *  - "OBC Open Championship": a live single-elimination event with scored
 *    matches (rally logs included), one match in progress, and a doubles draw;
 *  - "Netplay Round Robin Cup": an event open for registration with
 *    applications in every state (pending, awaiting payment, approved,
 *    rejected) and uploaded payment proofs;
 *  - "Holiday Doubles Classic": a draft event.
 *
 * Most players are guests with no user account, as at OBC. Running it again
 * does nothing for events that already exist.
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const LIVE_EVENT = 'OBC Open Championship';

    public const REGISTRATION_EVENT = 'Netplay Round Robin Cup';

    public const DRAFT_EVENT = 'Holiday Doubles Classic';

    private const PROOF_PATH = 'payment-proofs/demo-gcash-proof.png';

    /** @var list<User> */
    private array $umpires = [];

    public function run(): void
    {
        $this->call(AdminSeeder::class);

        $this->umpires = [
            $this->user('Ulysses Umpire', 'umpire1@netplay.test', 'umpire'),
            $this->user('Ursula Umpire', 'umpire2@netplay.test', 'umpire'),
        ];

        $this->seedLiveEvent();
        $this->seedRegistrationEvent();
        $this->seedDraftEvent();
    }

    private function seedLiveEvent(): void
    {
        if (Tournament::where('name', self::LIVE_EVENT)->exists()) {
            return;
        }

        $tournament = $this->tournament(self::LIVE_EVENT, 'single_elimination', [
            'date_start' => now()->subDay()->toDateString(),
            'date_end' => now()->addDay()->toDateString(),
            'registration_start' => now()->subDays(21)->toDateString(),
            'registration_end' => now()->subDays(3)->toDateString(),
        ]);

        // Level-C men's singles: a quarter-final round that is mostly played.
        $singles = $this->tournamentCategory($tournament, 'mens_singles', 'Level C', 300);

        $men = [
            ['Alex Reyes', 'male', 24, 'Level C', 14, 6],
            ['Bryan Ytang', 'male', 29, 'Level C', 10, 5],
            ['Carlo Mendoza', 'male', 22, 'Level C', 7, 7],
            ['Dante Villanueva', 'male', 31, 'Level C', 12, 3],
            ['Edgar Salazar', 'male', 27, 'Level C', 5, 8],
            ['Felix Bautista', 'male', 35, 'Level C', 9, 9],
            ['Gabriel Ocampo', 'male', 20, 'Level C', 3, 2],
            ['Harold Dizon', 'male', 26, 'Level C', 0, 0],
        ];

        foreach ($men as [$name, $gender, $age, $level, $wins, $losses]) {
            $player = $this->player($name, $gender, $age, $level);
            $this->statistic($player, $wins, $losses);
            $this->entry($singles, [$player], 'approved', 'verified');
        }

        // Mixed doubles: a four-pair draw that has not started yet.
        $doubles = $this->tournamentCategory($tournament, 'mixed_doubles', 'Level D', 400);

        $pairs = [
            [['Ivan Cabrera', 'male', 25, 'Level D'], ['Jasmine Lim', 'female', 23, 'Level D']],
            [['Kevin Uy', 'male', 28, 'Level D'], ['Lara Tan', 'female', 26, 'Level D']],
            [['Marco Dela Cruz', 'male', 33, 'Level D'], ['Nina Flores', 'female', 30, 'Level D']],
            [['Oscar Pineda', 'male', 21, 'Level D'], ['Paula Navarro', 'female', 21, 'Level D']],
        ];

        foreach ($pairs as $pair) {
            $this->entry($doubles, array_map(fn (array $p): Player => $this->player(...$p), $pair), 'approved', 'verified');
        }

        $scheduler = app(SingleEliminationScheduler::class);
        $scheduler->generate($singles);
        $scheduler->generate($doubles);

        $this->timetable($singles, '09:00');
        $this->timetable($doubles, '14:00');

        $this->playRound($singles, 1, [
            [[21, 14], [21, 17]],
            [[21, 19], [18, 21], [21, 16]],
            [[17, 21], [21, 15], [21, 18]],
            [[21, 9], [21, 12]],
        ]);

        // One quarter-final is under way and the rest wait their turn.
        $semiFinal = Matches::where('tournament_category_id', $singles->id)->where('round', 2)->where('position', 1)->first();

        if ($semiFinal && $semiFinal->matchApplications()->count() === 2) {
            $this->playGames($semiFinal, [[21, 18], [11, 9]], complete: false);
            $semiFinal->update(['status' => 'in_progress']);
        }
    }

    private function seedRegistrationEvent(): void
    {
        if (Tournament::where('name', self::REGISTRATION_EVENT)->exists()) {
            return;
        }

        $tournament = $this->tournament(self::REGISTRATION_EVENT, 'round_robin', [
            'date_start' => now()->addDays(14)->toDateString(),
            'date_end' => now()->addDays(15)->toDateString(),
            'registration_start' => now()->subDays(5)->toDateString(),
            'registration_end' => now()->addDays(9)->toDateString(),
        ]);

        // A walk-in-heavy singles draw showing every application state.
        $womens = $this->tournamentCategory($tournament, 'womens_singles', null, 250);

        $accountHolder = $this->player('Demo Player', 'female', 24, 'Level E');
        $accountHolder->update([
            'user_id' => $this->user('Demo Player', 'player@netplay.test', 'player')->id,
            'email' => 'player@netplay.test',
        ]);

        $applications = [
            [$accountHolder, 'approved', 'verified'],
            [$this->player('Queenie Aquino', 'female', 27, 'Level E'), 'approved', 'verified'],
            [$this->player('Rhea Santos', 'female', 22, 'Level F'), 'approved', 'verified'],
            [$this->player('Sheila Ramos', 'female', 30, 'Level D'), 'approved', 'verified'],
            [$this->player('Tessa Garcia', 'female', 19, 'Level F'), 'payment_pending', 'pending'],
            [$this->player('Uma Castillo', 'female', 25, 'Level E'), 'payment_pending', null],
            [$this->player('Vera Domingo', 'female', 28, 'Beginner'), 'pending_verification', null],
            [$this->player('Wendy Alcala', 'female', 34, 'Level G'), 'rejected', 'rejected'],
        ];

        foreach ($applications as [$player, $status, $payment]) {
            $application = $this->entry($womens, [$player], $status, $payment);

            if ($status === 'rejected') {
                $application->update(['rejection_reason' => 'The GCash reference number could not be matched to a payment.']);
            }
        }

        $mens = $this->tournamentCategory($tournament, 'mens_doubles', null, 500);

        $teams = [
            ['approved', 'verified', [['Xavier Lopez', 'male', 26, 'Level D'], ['Yuri Mercado', 'male', 28, 'Level D']]],
            ['approved', 'verified', [['Zandro Padilla', 'male', 32, 'Level C'], ['Arnel Soriano', 'male', 34, 'Level C']]],
            ['pending_verification', null, [['Benjie Cruz', 'male', 23, 'Level E'], ['Cris Valdez', 'male', 24, 'Level E']]],
        ];

        foreach ($teams as [$status, $payment, $members]) {
            $this->entry($mens, array_map(fn (array $m): Player => $this->player(...$m), $members), $status, $payment);
        }
    }

    private function seedDraftEvent(): void
    {
        if (Tournament::where('name', self::DRAFT_EVENT)->exists()) {
            return;
        }

        $tournament = $this->tournament(self::DRAFT_EVENT, 'double_elimination', [
            'date_start' => now()->addDays(45)->toDateString(),
            'date_end' => now()->addDays(46)->toDateString(),
            'registration_start' => now()->addDays(20)->toDateString(),
            'registration_end' => now()->addDays(38)->toDateString(),
        ], 'draft');

        $this->tournamentCategory($tournament, 'mixed_doubles', 'Level B', 450);
    }

    /**
     * @param  array<string, mixed>  $dates
     */
    private function tournament(string $name, string $format, array $dates, string $status = 'published'): Tournament
    {
        return Tournament::create([
            'name' => $name,
            'format' => $format,
            'sport' => 'Badminton',
            'location' => 'OBC Badminton Facility, Ozamiz City',
            'scoring_system' => 'Best of 3 to 21',
            'time' => '09:00',
            'status' => $status,
            ...$dates,
        ]);
    }

    /**
     * @param  key-of<Category::GENDER_TYPES>  $type
     */
    private function tournamentCategory(Tournament $tournament, string $type, ?string $level, float $fee): TournamentCategory
    {
        $definition = Category::GENDER_TYPES[$type];

        $category = Category::firstOrCreate(
            ['name' => $definition['name'], 'level' => $level, 'gender' => $definition['gender'], 'team_size' => $definition['team_size']],
        );

        return TournamentCategory::create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'registration_fee' => $fee,
            'max_slots' => 16,
            'status' => 'open',
        ]);
    }

    private function user(string $name, string $email, string $role): User
    {
        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->name = $name;
            $user->password = self::PASSWORD;
        }

        $user->role = $role;
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }

    /**
     * Reuse the player if one with this name exists (NETPLAY.md 4.3).
     */
    private function player(string $name, string $gender, int $age, string $level): Player
    {
        return Player::firstOrCreate(['name' => $name], [
            'gender' => $gender,
            'age' => $age,
            'birth_date' => now()->subYears($age)->subDays(crc32($name) % 300)->toDateString(),
            'level' => $level,
            'club' => 'OBC Badminton',
            'phone_number' => '09'.str_pad((string) (crc32($name) % 1000000000), 9, '0', STR_PAD_LEFT),
            'address' => 'Ozamiz City, Misamis Occidental',
        ]);
    }

    private function statistic(Player $player, int $wins, int $losses): void
    {
        PlayerStatistic::updateOrCreate(
            ['player_id' => $player->id],
            ['matches_played' => $wins + $losses, 'wins' => $wins, 'losses' => $losses],
        );
    }

    /**
     * Create an application for the players, with a payment in the given
     * state (null for none yet).
     *
     * @param  list<Player>  $players
     */
    private function entry(TournamentCategory $tournamentCategory, array $players, string $status, ?string $payment): PlayerApplication
    {
        $application = PlayerApplication::create([
            'tournament_category_id' => $tournamentCategory->id,
            'status' => $status,
        ]);

        foreach ($players as $player) {
            ApplicationPlayer::create([
                'application_id' => $application->id,
                'player_id' => $player->id,
                'level_at_apply' => $player->level ?? 'Beginner',
            ]);

            PlayerStatistic::firstOrCreate(['player_id' => $player->id]);
        }

        if ($payment !== null) {
            PaymentDetail::create([
                'application_id' => $application->id,
                'date_of_payment_made' => now()->subDays(random_int(4, 12)),
                'reference_number' => (string) random_int(1000000000000, 9999999999999),
                'payment_status' => $payment,
                'proof_of_payment' => $this->paymentProof(),
            ]);
        }

        return $application;
    }

    /**
     * A small placeholder GCash screenshot on the public disk, written once.
     */
    private function paymentProof(): string
    {
        $disk = Storage::disk('public');

        if ($disk->exists(self::PROOF_PATH) || ! function_exists('imagecreatetruecolor')) {
            return self::PROOF_PATH;
        }

        $image = imagecreatetruecolor(360, 480);
        imagefill($image, 0, 0, imagecolorallocate($image, 0, 110, 255));
        imagefilledrectangle($image, 30, 120, 330, 360, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 120, 150, 'GCash (demo)', imagecolorallocate($image, 0, 110, 255));
        imagestring($image, 4, 100, 220, 'Payment successful', imagecolorallocate($image, 40, 40, 40));

        ob_start();
        imagepng($image);
        $disk->put(self::PROOF_PATH, (string) ob_get_clean());

        return self::PROOF_PATH;
    }

    /**
     * Give a category's playable matches a court, time and umpire.
     */
    private function timetable(TournamentCategory $tournamentCategory, string $startTime): void
    {
        $matches = Matches::where('tournament_category_id', $tournamentCategory->id)
            ->where('status', 'scheduled')
            ->orderBy('round')
            ->orderBy('position')
            ->get();

        $start = today()->setTimeFromTimeString($startTime);

        foreach ($matches as $index => $match) {
            $match->update([
                'scheduled_at' => $start->copy()->addMinutes(45 * intdiv($index, 2)),
                'court' => 'Court '.(($index % 2) + 1),
                'assigned_umpire_id' => $this->umpires[$index % count($this->umpires)]->id,
            ]);
        }
    }

    /**
     * Play out every match of a round with the given per-game scores,
     * listed by match position. Scores are [side A, side B].
     *
     * @param  list<list<array{0: int, 1: int}>>  $results
     */
    private function playRound(TournamentCategory $tournamentCategory, int $round, array $results): void
    {
        $matches = Matches::where('tournament_category_id', $tournamentCategory->id)
            ->where('round', $round)
            ->orderBy('position')
            ->get();

        foreach ($matches as $index => $match) {
            if (isset($results[$index])) {
                $this->playGames($match, $results[$index]);
            }
        }
    }

    /**
     * Record games and their rally logs for a match. When `$complete` is
     * true the match is then completed; the MatchObserver advances the
     * winner and updates player statistics.
     *
     * @param  list<array{0: int, 1: int}>  $scores
     */
    private function playGames(Matches $match, array $scores, bool $complete = true): void
    {
        $match->load('matchApplications.application.applicationPlayers');

        [$sideA, $sideB] = [$match->matchApplications[0]->application, $match->matchApplications[1]->application];
        $gamesWon = [$sideA->id => 0, $sideB->id => 0];

        foreach ($scores as $index => [$pointsA, $pointsB]) {
            $isFinished = $pointsA >= 21 || $pointsB >= 21;
            $winner = $isFinished ? ($pointsA > $pointsB ? $sideA : $sideB) : null;

            $game = MatchGame::create([
                'match_id' => $match->id,
                'game_number' => $index + 1,
                'winner_application_id' => $winner?->id,
            ]);

            if ($winner) {
                $gamesWon[$winner->id]++;
            }

            $this->rallies($game, $sideA, $sideB, $pointsA, $pointsB);
        }

        if ($complete) {
            $winnerId = array_search(max($gamesWon), $gamesWon, true);
            $match->update(['status' => 'completed', 'winner_application_id' => $winnerId]);
        }
    }

    /**
     * Log one rally per point, in a shuffled order that ends on the game's
     * decisive point, crediting a random player on the scoring side.
     */
    private function rallies(MatchGame $game, PlayerApplication $sideA, PlayerApplication $sideB, int $pointsA, int $pointsB): void
    {
        $points = array_merge(array_fill(0, $pointsA, $sideA), array_fill(0, $pointsB, $sideB));
        $finalWinner = $pointsA > $pointsB ? $sideA : $sideB;
        $finished = $pointsA >= 21 || $pointsB >= 21;

        if ($finished) {
            // Hold back one point of the winner so the game ends exactly there.
            array_splice($points, array_search($finalWinner, $points, true), 1);
        }

        shuffle($points);

        if ($finished) {
            $points[] = $finalWinner;
        }

        foreach ($points as $number => $side) {
            Rally::create([
                'match_game_id' => $game->id,
                'scoring_player_id' => $side->applicationPlayers->random()->player_id,
                'rally_number' => $number + 1,
            ]);
        }
    }
}
