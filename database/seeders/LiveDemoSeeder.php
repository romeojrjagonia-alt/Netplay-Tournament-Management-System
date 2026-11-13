<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerLinkRequest;
use App\Models\PlayerStatistic;
use App\Models\Rally;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Badminton\GameRules;
use App\Support\Scheduling\RoundRobinScheduler;
use App\Support\Scheduling\SingleEliminationScheduler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A fully populated system for a live walk-through of every feature. It is
 * never called from DatabaseSeeder; run it explicitly:
 *
 *   php artisan db:seed --class=LiveDemoSeeder
 *
 * It signs you in as nobody: log in with the accounts it prints (all share the
 * password DemoPass123, or DEMO_SEED_PASSWORD if you set one). Mail addresses
 * use the reserved demo.netplay.test domain, so no email ever leaves the app.
 *
 * What it sets up, and what to show with it:
 *
 * - Admin queue: "OBC Summer Open" is accepting registrations. Men's Singles
 *   has eight approved entries of very different level, win rate and match
 *   history (NOT drawn yet, so the statistic seeding and the drag-to-arrange
 *   bracket editor can be shown), plus one application in every review state
 *   (new, awaiting payment check, rejected receipt, rejected outright).
 *   Women's Singles is already drawn with byes; Men's Doubles has pairs to
 *   verify; Mixed Doubles is full.
 * - Umpire side: "Round Robin Showdown" is under way with completed matches
 *   (scoresheets), one being scored right now, and upcoming ones, all for
 *   Umpire Ramon. Unnotified schedules light up "Email players".
 * - Player side: "Player Spotlight Cup" gives Alex Rivera a concluded win, a
 *   concluded loss, a live match and an upcoming one.
 * - Concluded event: "Smash Knockout Classic" has a champion, who moves up a level.
 * - A second sport: "Pickleball Fun Cup" uses pickleball scoring (games to 11).
 * - Draft event: "Winter Draft" has not been published.
 * - Users page: a guest record waiting on a link request, a pair of likely
 *   duplicate walk-ins, and an unverified account that sees the verify prompt.
 * - Walk-ins: most entries are players without accounts. Their reference codes
 *   are printed so "Check Applications" and the payment page can be shown.
 *
 * Re-running skips tournaments that already exist and never changes an
 * existing account's password. Remove everything it made with:
 *
 *   DEMO_SEED_PURGE=true php artisan db:seed --class=LiveDemoSeeder
 */
class LiveDemoSeeder extends Seeder
{
    public const EMAIL_DOMAIN = 'demo.netplay.test';

    public const DEFAULT_PASSWORD = 'DemoPass123';

    public const PROOF_PATH = 'payment-proofs/demo-receipt.png';

    /**
     * @var list<string>
     */
    public const TOURNAMENTS = [
        'OBC Summer Open',
        'Round Robin Showdown',
        'Smash Knockout Classic',
        'Pickleball Fun Cup',
        'Player Spotlight Cup',
        'Winter Draft',
    ];

    private string $password;

    /**
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private array $accounts = [];

    /**
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private array $references = [];

    private int $playerCounter = 0;

    /** Points a game is played to: 21 for badminton, 11 for pickleball. */
    private int $gamePoints = 21;

    public function run(): void
    {
        if (filter_var(env('DEMO_SEED_PURGE', false), FILTER_VALIDATE_BOOL)) {
            $this->purge();

            return;
        }

        $configured = env('DEMO_SEED_PASSWORD');
        $this->password = is_string($configured) && $configured !== '' ? $configured : self::DEFAULT_PASSWORD;

        $this->call(AdminSeeder::class);
        $this->ensureProofImage();

        $umpire = $this->account('ramon.umpire', 'Umpire Ramon Cruz', 'umpire');
        $this->account('gwen.umpire', 'Umpire Gwen Tan', 'umpire');

        $alex = $this->account('alex.rivera', 'Alex Rivera', 'player');
        $bianca = $this->account('bianca.lopez', 'Bianca Lopez', 'player');
        $carlo = $this->account('carlo.mendoza', 'Carlo Mendoza', 'player');
        $dana = $this->account('dana.ortiz', 'Dana Ortiz', 'player', verified: false);

        $accountPlayers = [
            'alex' => $this->player('Alex Rivera', 'male', $alex->email, $alex->id),
            'bianca' => $this->player('Bianca Lopez', 'female', $bianca->email, $bianca->id),
            'carlo' => $this->player('Carlo Mendoza', 'male', $carlo->email, $carlo->id),
            'dana' => $this->player('Dana Ortiz', 'female', $dana->email, $dana->id),
        ];

        $this->seedSummerOpen($accountPlayers);
        $this->seedRoundRobinShowdown($umpire, $accountPlayers);
        $this->seedSmashClassic();
        $this->seedPickleballCup($accountPlayers);
        $this->seedPlayerSpotlight($umpire, $accountPlayers);
        $this->seedWinterDraft();
        $this->seedUsersPage($carlo);

        $this->report();
    }

    /**
     * Registration open. Men's Singles is undrawn with eight entries ranked
     * by level, win rate and history; every review state is represented.
     *
     * @param  array<string, Player>  $people
     */
    private function seedSummerOpen(array $people): void
    {
        $tournament = $this->tournament('OBC Summer Open', 'single_elimination', [
            'date_start' => now()->addDays(21)->toDateString(),
            'date_end' => now()->addDays(23)->toDateString(),
            'registration_start' => now()->subDays(7)->toDateString(),
            'registration_end' => now()->addDays(14)->toDateString(),
        ]);

        if (! $tournament) {
            return;
        }

        $mensSingles = $this->tournamentCategory($tournament, "Men's Singles", 'male', 1, 'Level C', 200, 16);
        $womensSingles = $this->tournamentCategory($tournament, "Women's Singles", 'female', 1, 'Level C', 200, 16);
        $mensDoubles = $this->tournamentCategory($tournament, "Men's Doubles", 'male', 2, 'Level C', 400, 16);
        $mixedDoubles = $this->tournamentCategory($tournament, 'Mixed Doubles', 'mixed', 2, 'Level C', 400, 2);

        // Approved weakest-first, so seeding by strength is visibly different
        // from the order people signed up in. [name, level, wins, losses]
        foreach ([
            ['Wally Newbie', 'Beginner', 0, 0],
            ['Ian Intermediate', 'Intermediate', 3, 2],
            ['Gabe Grinder', 'Level D', 12, 12],
            ['Dante Steady', 'Level D', 6, 4],
            ['Dominic Dominant', 'Level D', 18, 2],
            ['Cesar Climber', 'Level C', 5, 5],
            ['Bryan Ranked', 'Level B', 9, 3],
            ['Alex Ace', 'Level A', 20, 4],
        ] as [$name, $level, $wins, $losses]) {
            $this->approvedEntry($mensSingles, [$this->player($name, 'male', level: $level, wins: $wins, losses: $losses)]);
        }

        // One application per review state, for the admin queue.
        $this->application($mensSingles, [$people['alex']], 'pending_verification');

        $awaitingReview = $this->application($mensSingles, [$this->player('Jun Pascual', 'male')], 'payment_pending');
        $this->payment($awaitingReview, 'pending', 'GCASH-DEMO-0001');

        $badReceipt = $this->application($mensSingles, [$this->player('Lito Mercado', 'male')], 'payment_pending');
        $this->payment($badReceipt, 'rejected', 'GCASH-DEMO-0002');

        $this->application($mensSingles, [$this->player('Vic Robles', 'male')], 'rejected', 'Below the minimum age for this category.');

        // Women's Singles: six approved, drawn (two byes), one match played.
        $this->approvedEntry($womensSingles, [$people['bianca']]);

        foreach (['Lina Cruz', 'Mara Soriano', 'Tess Villar', 'Joy Alonzo', 'Nena Bautista'] as $name) {
            $this->approvedEntry($womensSingles, [$this->player($name, 'female')]);
        }

        app(SingleEliminationScheduler::class)->generate($womensSingles);

        $this->orderedMatches($womensSingles)
            ->filter(fn (Matches $match): bool => $match->round === 1 && $match->status === 'scheduled')
            ->take(1)
            ->each(fn (Matches $match) => $this->completeMatch($match, 0, 2));

        // Men's Doubles: pairs waiting on verification, one with a signed-in player.
        $this->application($mensDoubles, [$people['alex'], $this->player('Ben Aquino', 'male')], 'pending_verification');
        $this->application($mensDoubles, [$this->player('Ian Gomez', 'male'), $this->player('Ken Ramos', 'male')], 'pending_verification');

        // Mixed Doubles: both slots taken, so the category shows as full.
        foreach (['Rolly', 'Pido'] as $name) {
            $this->approvedEntry($mixedDoubles, [
                $this->player("$name Tan", 'male'),
                $this->player(($name === 'Rolly' ? 'Gina' : 'Rosa').' Tan', 'female'),
            ]);
        }
    }

    /**
     * Ongoing round robin for the umpire: completed matches, one in progress,
     * the rest upcoming with courts and an umpire but not yet emailed.
     *
     * @param  array<string, Player>  $people
     */
    private function seedRoundRobinShowdown(User $umpire, array $people): void
    {
        $tournament = $this->tournament('Round Robin Showdown', 'round_robin', [
            'date_start' => now()->subDay()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
            'registration_start' => now()->subDays(21)->toDateString(),
            'registration_end' => now()->subDays(3)->toDateString(),
        ]);

        if (! $tournament) {
            return;
        }

        $mensSingles = $this->tournamentCategory($tournament, "Men's Singles", 'male', 1, 'Level C', 250, 16);
        $womensSingles = $this->tournamentCategory($tournament, "Women's Singles", 'female', 1, 'Level C', 250, 16);

        $this->approvedEntry($mensSingles, [$people['carlo']]);

        foreach (['Rico Dalisay', 'Sandro Peña', 'Tomas Ilagan', 'Victor Salas', 'Warren Ocampo'] as $name) {
            $this->approvedEntry($mensSingles, [$this->player($name, 'male')]);
        }

        $this->approvedEntry($womensSingles, [$people['dana']]);

        foreach (['Mara Gonzales', 'Nina Reyes', 'Olive Sison'] as $name) {
            $this->approvedEntry($womensSingles, [$this->player($name, 'female')]);
        }

        app(RoundRobinScheduler::class)->generate($mensSingles);
        app(RoundRobinScheduler::class)->generate($womensSingles);

        $upcoming = now()->addDay()->setTime(9, 0);

        // Completed games credit player statistics through MatchObserver, so
        // matches are created as scheduled and only completed afterwards.
        $this->orderedMatches($mensSingles)->each(function (Matches $match, int $index) use ($umpire, &$upcoming): void {
            $official = [
                'assigned_umpire_id' => $umpire->id,
                'line_judge_1_name' => 'Pedro Reyes',
                'line_judge_2_name' => 'Marites Lim',
                'court' => (string) (($index % 2) + 1),
            ];

            if ($index < 6) {
                $match->update($official + ['scheduled_at' => now()->subDay()->setTime(9, 0)->addMinutes($index * 40)]);
                $this->completeMatch($match, $index % 2, $index % 3 === 0 ? 3 : 2);

                return;
            }

            if ($index === 6) {
                $match->update($official + ['status' => 'in_progress', 'scheduled_at' => now()->subMinutes(20)]);

                return;
            }

            $match->update($official + ['scheduled_at' => $upcoming->copy()]);
            $upcoming->addMinutes(40);
        });

        $this->orderedMatches($womensSingles)->each(function (Matches $match, int $index) use ($umpire): void {
            $match->update([
                'scheduled_at' => now()->addDay()->setTime(14, 0)->addMinutes($index * 40),
                'court' => (string) (($index % 2) + 3),
                'assigned_umpire_id' => $umpire->id,
            ]);
        });
    }

    /**
     * Concluded single elimination with a champion. Completing the final
     * moves the winner up one level.
     */
    private function seedSmashClassic(): void
    {
        $tournament = $this->tournament('Smash Knockout Classic', 'single_elimination', [
            'date_start' => now()->subDays(30)->toDateString(),
            'date_end' => now()->subDays(28)->toDateString(),
            'registration_start' => now()->subDays(60)->toDateString(),
            'registration_end' => now()->subDays(35)->toDateString(),
        ]);

        if (! $tournament) {
            return;
        }

        $mensSingles = $this->tournamentCategory($tournament, "Men's Singles", 'male', 1, 'Level D', 200, 8);

        foreach (['Dado Villar', 'Eddie Navarro', 'Fidel Zamora', 'Gil Esteban'] as $name) {
            $this->approvedEntry($mensSingles, [$this->player($name, 'male', level: 'Level D')]);
        }

        app(SingleEliminationScheduler::class)->generate($mensSingles);

        // Round 1 first, then the final, so each winner is already in the
        // final when that match is read.
        foreach ([[1, 1], [1, 2], [2, 1]] as [$round, $position]) {
            $match = Matches::where('tournament_category_id', $mensSingles->id)
                ->where('round', $round)
                ->where('position', $position)
                ->first();

            $match->update([
                'scheduled_at' => now()->subDays(29)->setTime(9, 0)->addHours($round * 2 + $position),
                'court' => (string) $position,
            ]);

            $this->completeMatch($match, 0, 2);
        }
    }

    /**
     * A second sport: pickleball scoring (games to 11). One match is played so
     * the scoreboard and scoresheet show pickleball scores.
     *
     * @param  array<string, Player>  $people
     */
    private function seedPickleballCup(array $people): void
    {
        $tournament = $this->tournament('Pickleball Fun Cup', 'single_elimination', [
            'date_start' => now()->addDays(10)->toDateString(),
            'date_end' => now()->addDays(11)->toDateString(),
            'registration_start' => now()->subDays(5)->toDateString(),
            'registration_end' => now()->addDays(6)->toDateString(),
        ], sport: 'Pickleball', scoringSystem: GameRules::SYSTEM_PICKLEBALL);

        if (! $tournament) {
            return;
        }

        $doubles = $this->tournamentCategory($tournament, "Men's Doubles", 'male', 2, 'Level D', 300, 8);

        foreach ([['Hugo Ferrer', 'Ivan Ledesma'], ['Jared Soto', 'Kyle Abella'], ['Leo Mabini', 'Marc Evangelista'], ['Noel Quiroz', 'Owen Castillo']] as $pair) {
            $this->approvedEntry($doubles, array_map(fn (string $name): Player => $this->player($name, 'male', level: 'Level D'), $pair));
        }

        $this->application($doubles, [$people['carlo'], $this->player('Paolo Dizon', 'male')], 'pending_verification');

        app(SingleEliminationScheduler::class)->generate($doubles);

        $this->gamePoints = 11;

        $this->orderedMatches($doubles)
            ->filter(fn (Matches $match): bool => $match->round === 1)
            ->take(1)
            ->each(function (Matches $match): void {
                $match->update(['scheduled_at' => now()->subHour(), 'court' => '1']);
                $this->completeMatch($match, 0, 3);
            });

        $this->gamePoints = 21;
    }

    /**
     * Alex Rivera's view of every match-card state: a concluded win, a
     * concluded loss in three games, a match being scored right now and one
     * still upcoming.
     *
     * @param  array<string, Player>  $people
     */
    private function seedPlayerSpotlight(User $umpire, array $people): void
    {
        $tournament = $this->tournament('Player Spotlight Cup', 'round_robin', [
            'date_start' => now()->subDay()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
            'registration_start' => now()->subDays(21)->toDateString(),
            'registration_end' => now()->subDays(3)->toDateString(),
        ]);

        if (! $tournament) {
            return;
        }

        $category = $this->tournamentCategory($tournament, "Men's Singles", 'male', 1, 'Level C', 250, 16);

        $mine = $this->approvedEntry($category, [$people['alex']]);

        foreach (['Jose Villanueva', 'Kiko Ramirez', 'Lando Cabrera', 'Mon Suarez'] as $name) {
            $this->approvedEntry($category, [$this->player($name, 'male')]);
        }

        app(RoundRobinScheduler::class)->generate($category);

        $matches = $this->orderedMatches($category);
        $isMine = fn (Matches $match): bool => $match->matchApplications()->where('application_id', $mine->id)->exists();
        $mineMatches = $matches->filter($isMine)->values();

        $matches->each(function (Matches $match, int $index) use ($umpire): void {
            $match->update([
                'scheduled_at' => now()->addDay()->setTime(9, 0)->addMinutes($index * 40),
                'court' => (string) (($index % 2) + 1),
                'assigned_umpire_id' => $umpire->id,
            ]);
        });

        $myIndexIn = fn (Matches $match): int => $match->matchApplications()->get()
            ->search(fn ($entry) => $entry->application_id === $mine->id);

        $won = $mineMatches[0];
        $won->update(['scheduled_at' => now()->subHours(5)]);
        $this->completeMatch($won, $myIndexIn($won), 2);

        $lost = $mineMatches[1];
        $lost->update(['scheduled_at' => now()->subHours(3)]);
        $this->completeMatch($lost, 1 - $myIndexIn($lost), 3);

        $live = $mineMatches[2];
        $opponentId = $live->matchApplications()->get()
            ->first(fn ($entry) => $entry->application_id !== $mine->id)->application_id;

        $live->update(['status' => 'in_progress', 'scheduled_at' => now()->subMinutes(20)]);
        $this->recordGame($live, 1, $mine->id, $opponentId, 21, 18, true);
        $this->recordGame($live, 2, $opponentId, $mine->id, 12, 10, false);
    }

    private function seedWinterDraft(): void
    {
        $tournament = $this->tournament('Winter Draft', 'round_robin', [
            'date_start' => now()->addDays(90)->toDateString(),
            'date_end' => now()->addDays(92)->toDateString(),
            'registration_start' => now()->addDays(60)->toDateString(),
            'registration_end' => now()->addDays(80)->toDateString(),
        ], 'draft');

        if ($tournament) {
            $this->tournamentCategory($tournament, 'Mixed Doubles', 'mixed', 2, 'Level B', 400, 16);
        }
    }

    /**
     * Things for the admin Users page: a guest record a player has asked to
     * claim, and walk-ins who look like the same person entered twice.
     */
    private function seedUsersPage(User $carlo): void
    {
        $guest = Player::firstOrCreate(['email' => 'carlos.mendoza@'.self::EMAIL_DOMAIN], [
            'name' => 'Carlos Mendoza',
            'birth_date' => now()->subYears(27)->toDateString(),
            'gender' => 'male',
            'phone_number' => '09170001234',
            'address' => 'Ozamiz City',
            'level' => 'Level C',
        ]);

        PlayerLinkRequest::firstOrCreate(
            ['user_id' => $carlo->id, 'player_id' => $guest->id],
            ['status' => PlayerLinkRequest::PENDING],
        );

        foreach ([['Maria Santos', 'maria.santos@'.self::EMAIL_DOMAIN], ['Maria S. Santos', 'm.santos@'.self::EMAIL_DOMAIN]] as [$name, $email]) {
            Player::firstOrCreate(['email' => $email], [
                'name' => $name,
                'birth_date' => '1995-04-12',
                'gender' => 'female',
                'phone_number' => '09170005678',
                'address' => 'Ozamiz City',
                'level' => 'Level D',
            ]);
        }
    }

    /**
     * Returns null when the tournament already exists, so a re-run skips its
     * whole block instead of duplicating entries.
     *
     * @param  array<string, string>  $dates
     */
    private function tournament(string $name, string $format, array $dates, string $status = 'published', string $sport = 'Badminton', string $scoringSystem = GameRules::SYSTEM_BEST_OF_THREE): ?Tournament
    {
        if (Tournament::where('name', $name)->exists()) {
            $this->command?->warn("Skipped \"{$name}\": already seeded.");

            return null;
        }

        return Tournament::create([
            'name' => $name,
            'format' => $format,
            'sport' => $sport,
            'location' => 'OBC Badminton Facility, Ozamiz City',
            'scoring_system' => $scoringSystem,
            'time' => '9:00 AM - 5:00 PM',
            'specific_days' => 'Sat, Sun',
            'status' => $status,
            'tiebreaker_rules' => ['head_to_head', 'point_differential'],
            ...$dates,
        ]);
    }

    private function tournamentCategory(Tournament $tournament, string $name, string $gender, int $teamSize, string $level, int $fee, int $slots): TournamentCategory
    {
        $category = Category::firstOrCreate(
            ['name' => $name, 'gender' => $gender, 'team_size' => $teamSize, 'level' => $level],
            ['age_min' => null, 'age_max' => null],
        );

        return TournamentCategory::create([
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'registration_fee' => $fee,
            'max_slots' => $slots,
            'status' => 'open',
        ]);
    }

    private function account(string $localPart, string $name, string $role, bool $verified = true): User
    {
        $email = $localPart.'@'.self::EMAIL_DOMAIN;

        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => $this->password,
            'role' => $role,
            'email_verified_at' => $verified ? now() : null,
        ]);

        $this->accounts[] = [
            $role,
            $email,
            $user->wasRecentlyCreated ? 'new' : 'existing (password unchanged)',
            $verified ? 'verified' : 'NOT verified (shows the verify prompt)',
        ];

        return $user;
    }

    /**
     * Players are reused by email, mirroring the search-first rule for real
     * registrations: re-running never splits a player's match history.
     */
    private function player(string $name, string $gender, ?string $email = null, ?int $userId = null, string $level = 'Level C', ?int $wins = null, ?int $losses = null): Player
    {
        $email ??= str($name)->slug('.').'@'.self::EMAIL_DOMAIN;

        $player = Player::firstOrCreate(['email' => $email], [
            'user_id' => $userId,
            'name' => $name,
            'birth_date' => now()->subYears(20 + $this->playerCounter % 15)->subDays($this->playerCounter * 11 % 300)->toDateString(),
            'gender' => $gender,
            'phone_number' => '09'.str_pad((string) (170000000 + ++$this->playerCounter), 9, '0', STR_PAD_LEFT),
            'address' => 'Ozamiz City',
            'level' => $level,
        ]);

        if ($wins !== null && $losses !== null) {
            PlayerStatistic::updateOrCreate(
                ['player_id' => $player->id],
                ['matches_played' => $wins + $losses, 'wins' => $wins, 'losses' => $losses],
            );
        }

        return $player;
    }

    /**
     * @param  list<Player>  $players
     */
    private function application(TournamentCategory $tournamentCategory, array $players, string $status, ?string $rejectionReason = null): PlayerApplication
    {
        $application = PlayerApplication::create([
            'tournament_category_id' => $tournamentCategory->id,
            'status' => $status,
            'rejection_reason' => $rejectionReason,
        ]);

        foreach ($players as $player) {
            ApplicationPlayer::create([
                'application_id' => $application->id,
                'player_id' => $player->id,
                'level_at_apply' => $player->level ?? 'Beginner',
                'id_document_type' => 'national_id',
                'id_document_path' => self::PROOF_PATH,
            ]);
        }

        if (in_array($status, ['pending_verification', 'payment_pending'], true) && count($this->references) < 6) {
            $this->references[] = [$application->reference_code, $players[0]->name, str_replace('_', ' ', $status)];
        }

        return $application;
    }

    /**
     * An entry that passed verification and has a verified payment.
     *
     * @param  list<Player>  $players
     */
    private function approvedEntry(TournamentCategory $tournamentCategory, array $players): PlayerApplication
    {
        $application = $this->application($tournamentCategory, $players, 'approved');

        $this->payment($application, 'verified', 'GCASH-DEMO-'.str_pad((string) $application->id, 4, '0', STR_PAD_LEFT));

        return $application;
    }

    private function payment(PlayerApplication $application, string $status, string $reference): void
    {
        PaymentDetail::create([
            'application_id' => $application->id,
            'date_of_payment_made' => now()->subDay(),
            'reference_number' => $reference,
            'payment_status' => $status,
            'proof_of_payment' => self::PROOF_PATH,
        ]);
    }

    /**
     * @return Collection<int, Matches>
     */
    private function orderedMatches(TournamentCategory $tournamentCategory): Collection
    {
        return Matches::where('tournament_category_id', $tournamentCategory->id)
            ->orderBy('round')
            ->orderBy('position')
            ->get();
    }

    /**
     * Record the games and mark the match completed, in that order, so the
     * MatchObserver credits statistics and advances a knockout winner.
     *
     * @param  int  $winnerIndex  0 for the first listed entry, 1 for the second
     * @param  int  $gamesPlayed  2 (a straight-games win) or 3
     */
    private function completeMatch(Matches $match, int $winnerIndex, int $gamesPlayed): void
    {
        $entries = $match->matchApplications()->get();

        if ($entries->count() < 2) {
            return;
        }

        $winnerId = $entries[$winnerIndex]->application_id;
        $loserId = $entries[1 - $winnerIndex]->application_id;

        $gameWinners = $gamesPlayed === 3 ? [$loserId, $winnerId, $winnerId] : [$winnerId, $winnerId];

        // The loser's points in each game, always short of a win by two.
        $trailerPoints = $this->gamePoints === 11 ? [7, 4, 9, 5] : [17, 12, 19, 15];

        foreach ($gameWinners as $index => $gameWinnerId) {
            $this->recordGame(
                $match,
                $index + 1,
                $gameWinnerId,
                $gameWinnerId === $winnerId ? $loserId : $winnerId,
                $this->gamePoints,
                $trailerPoints[($match->id + $index) % count($trailerPoints)],
                true,
            );
        }

        $match->update(['status' => 'completed', 'winner_application_id' => $winnerId]);
    }

    /**
     * Create a game with a full rally log, so point scores show on the match
     * cards and the scoresheet. The trailing entry's points are spread evenly
     * through the game and a decided game ends on the leader's point.
     *
     * @param  bool  $decided  false leaves the game in progress, with no winner
     */
    private function recordGame(Matches $match, int $gameNumber, int $leaderId, int $trailerId, int $leaderPoints, int $trailerPoints, bool $decided): void
    {
        $game = MatchGame::create([
            'match_id' => $match->id,
            'game_number' => $gameNumber,
            'winner_application_id' => $decided ? $leaderId : null,
        ]);

        $leaderPlayers = ApplicationPlayer::where('application_id', $leaderId)->pluck('player_id')->all();
        $trailerPlayers = ApplicationPlayer::where('application_id', $trailerId)->pluck('player_id')->all();

        $total = $leaderPoints + $trailerPoints;

        $trailerRallies = [];

        for ($point = 0; $point < $trailerPoints; $point++) {
            $trailerRallies[(int) floor(($point + 0.5) * $total / $trailerPoints)] = true;
        }

        $leaderScored = 0;
        $trailerScored = 0;

        for ($index = 0; $index < $total; $index++) {
            if (isset($trailerRallies[$index])) {
                $scorerId = $trailerPlayers[$trailerScored++ % count($trailerPlayers)];
            } else {
                $scorerId = $leaderPlayers[$leaderScored++ % count($leaderPlayers)];
            }

            Rally::create([
                'match_game_id' => $game->id,
                'scoring_player_id' => $scorerId,
                'rally_number' => $index + 1,
            ]);
        }
    }

    /**
     * A 1x1 PNG stands in for the uploaded ID photos and GCash screenshots, so
     * "View Attachment" resolves. Written through the public disk.
     */
    private function ensureProofImage(): void
    {
        $disk = Storage::disk('public');

        if (! $disk->exists(self::PROOF_PATH)) {
            $disk->put(self::PROOF_PATH, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
            ));
        }
    }

    private function purge(): void
    {
        $tournaments = Tournament::whereIn('name', self::TOURNAMENTS)->get();

        // Cascades through tournament categories, applications, entries,
        // matches, games and payments; players are restricted until then.
        $tournaments->each->delete();

        $links = PlayerLinkRequest::whereHas('user', fn ($query) => $query->where('email', 'like', '%@'.self::EMAIL_DOMAIN))->delete();
        $players = Player::where('email', 'like', '%@'.self::EMAIL_DOMAIN)->delete();
        $users = User::where('email', 'like', '%@'.self::EMAIL_DOMAIN)->delete();

        Storage::disk('public')->delete(self::PROOF_PATH);

        $this->command?->info("Purged {$tournaments->count()} tournaments, {$players} players, {$users} users and {$links} link requests.");
        $this->command?->line('The admin account and shared categories are left in place.');
    }

    private function report(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('Live demo data seeded.');
        $this->command->table(['Role', 'Email', 'State', 'Email check'], array_merge(
            [['admin', AdminSeeder::EMAIL, 'password '.AdminSeeder::PASSWORD, 'verified']],
            $this->accounts,
        ));
        $this->command->line("All demo accounts use the password: {$this->password}");

        if ($this->references !== []) {
            $this->command->newLine();
            $this->command->info('Reference codes for "Check Applications" on the landing page:');
            $this->command->table(['Code', 'Applicant', 'Status'], $this->references);
        }
    }
}
