<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Scheduling\RoundRobinScheduler;
use App\Support\Scheduling\SingleEliminationScheduler;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Test data for hand-testing a deployed environment. It is never called
 * from DatabaseSeeder; run it explicitly:
 *
 *   php artisan db:seed --class=DeployTestDataSeeder --force
 *
 * Everything it creates is recognisable and removable:
 * - tournaments are named "[TEST] ..."
 * - users and players use the @seed.netplay.test email domain, which is a
 *   reserved TLD, so "Email players" and verification mail go nowhere
 * - the payment-proof image is payment-proofs/seed-test-receipt.png
 *
 * Remove it all again with:
 *
 *   TEST_SEED_PURGE=true php artisan db:seed --class=DeployTestDataSeeder --force
 *
 * Accounts: admin, two umpires and three players, all email-verified.
 * Set TEST_SEED_PASSWORD to choose their password; otherwise a random one
 * is generated and printed once. Re-running never changes the password of
 * an account that already exists, and never duplicates a seeded tournament.
 *
 * Tournaments:
 * - "[TEST] Round Robin Open": ongoing, registration closed. Men's Singles
 *   is part-way through (completed, in-progress and upcoming matches with
 *   courts and umpires); Women's Singles is scheduled but unplayed.
 * - "[TEST] Knockout Cup": registration open. Men's Singles has approved
 *   entries and NO bracket yet, plus applications in every review state;
 *   Women's Singles has a generated bracket with byes; Mixed Doubles is full.
 * - "[TEST] Past Classic": concluded single elimination, champion decided.
 * - "[TEST] Spring Draft": unpublished draft with an empty category.
 */
class DeployTestDataSeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'seed.netplay.test';

    private const NAME_PREFIX = '[TEST] ';

    private const PROOF_PATH = 'payment-proofs/seed-test-receipt.png';

    private string $password;

    private bool $passwordWasGenerated = false;

    /**
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private array $accounts = [];

    private int $playerCounter = 0;

    public function run(): void
    {
        if (filter_var(env('TEST_SEED_PURGE', false), FILTER_VALIDATE_BOOL)) {
            $this->purge();

            return;
        }

        $configured = env('TEST_SEED_PASSWORD');

        $this->passwordWasGenerated = ! is_string($configured) || $configured === '';
        $this->password = $this->passwordWasGenerated ? Str::password(16, symbols: false) : $configured;

        $this->ensureProofImage();

        $admin = $this->account('admin@'.self::EMAIL_DOMAIN, 'Test Admin', 'admin');
        $umpire = $this->account('umpire1@'.self::EMAIL_DOMAIN, 'Test Umpire One', 'umpire');
        $this->account('umpire2@'.self::EMAIL_DOMAIN, 'Test Umpire Two', 'umpire');

        $playerUsers = [
            $this->account('player1@'.self::EMAIL_DOMAIN, 'Test Player One', 'player'),
            $this->account('player2@'.self::EMAIL_DOMAIN, 'Test Player Two', 'player'),
            $this->account('player3@'.self::EMAIL_DOMAIN, 'Test Player Three', 'player'),
        ];

        $loginPlayers = collect($playerUsers)->map(fn (User $user, int $index): Player => $this->player(
            $user->name,
            $index === 1 ? 'female' : 'male',
            email: $user->email,
            userId: $user->id,
        ))->all();

        $this->seedRoundRobinOpen($umpire, $loginPlayers);
        $this->seedKnockoutCup($loginPlayers);
        $this->seedPastClassic();
        $this->seedSpringDraft();

        $this->report($admin);
    }

    /**
     * Ongoing round robin: Men's Singles part-way through, Women's Singles
     * scheduled but unplayed.
     *
     * @param  list<Player>  $loginPlayers
     */
    private function seedRoundRobinOpen(User $umpire, array $loginPlayers): void
    {
        $tournament = $this->tournament('Round Robin Open', 'round_robin', [
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

        $this->approvedEntry($mensSingles, [$loginPlayers[0]]);

        foreach (range(1, 5) as $n) {
            $this->approvedEntry($mensSingles, [$this->player("Rico Dalisay $n", 'male')]);
        }

        $this->approvedEntry($womensSingles, [$loginPlayers[1]]);

        foreach (range(1, 3) as $n) {
            $this->approvedEntry($womensSingles, [$this->player("Mara Soriano $n", 'female')]);
        }

        app(RoundRobinScheduler::class)->generate($mensSingles);
        app(RoundRobinScheduler::class)->generate($womensSingles);

        $upcoming = now()->addDay()->setTime(9, 0);

        // Completed games credit player statistics through MatchObserver, so
        // matches are created as scheduled and only completed afterwards.
        $this->orderedMatches($mensSingles)->each(function (Matches $match, int $index) use ($umpire, &$upcoming): void {
            $court = (string) (($index % 2) + 1);

            if ($index < 6) {
                $match->update([
                    'scheduled_at' => now()->subDay()->setTime(9, 0)->addMinutes($index * 40),
                    'court' => $court,
                    'assigned_umpire_id' => $umpire->id,
                ]);
                $this->completeMatch($match, $index % 2, $index % 3 === 0 ? 3 : 2);

                return;
            }

            if ($index === 6) {
                $match->update([
                    'status' => 'in_progress',
                    'scheduled_at' => now()->subMinutes(20),
                    'court' => $court,
                    'assigned_umpire_id' => $umpire->id,
                ]);

                return;
            }

            $match->update([
                'scheduled_at' => $upcoming->copy(),
                'court' => $court,
                'assigned_umpire_id' => $umpire->id,
            ]);

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
     * Registration open: one category left ungenerated for testing the draw,
     * one already generated with byes, and applications in every review state.
     *
     * @param  list<Player>  $loginPlayers
     */
    private function seedKnockoutCup(array $loginPlayers): void
    {
        $tournament = $this->tournament('Knockout Cup', 'single_elimination', [
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

        // Men's Singles: 5 approved (bracket of 8, 3 byes) and NOT generated yet.
        foreach (range(1, 5) as $n) {
            $this->approvedEntry($mensSingles, [$this->player("Nico Alvarez $n", 'male')]);
        }

        // One application per review state, for the admin queue.
        $this->application($mensSingles, [$loginPlayers[0]], 'pending_verification');

        $awaitingReview = $this->application($mensSingles, [$this->player('Jun Pascual', 'male')], 'payment_pending');
        $this->payment($awaitingReview, 'pending', 'GCASH-TEST-0001');

        $badReceipt = $this->application($mensSingles, [$this->player('Lito Mercado', 'male')], 'payment_pending');
        $this->payment($badReceipt, 'rejected', 'GCASH-TEST-0002');

        $this->application($mensSingles, [$this->player('Vic Robles', 'male')], 'rejected', 'Below the minimum age for this category.');

        // Women's Singles: 6 approved, bracket generated (2 byes), round 1 part-played.
        $this->approvedEntry($womensSingles, [$loginPlayers[1]]);

        foreach (range(1, 5) as $n) {
            $this->approvedEntry($womensSingles, [$this->player("Lina Cruz $n", 'female')]);
        }

        app(SingleEliminationScheduler::class)->generate($womensSingles);

        $this->orderedMatches($womensSingles)
            ->filter(fn (Matches $match): bool => $match->round === 1 && $match->status === 'scheduled')
            ->take(1)
            ->each(fn (Matches $match) => $this->completeMatch($match, 0, 2));

        // Men's Doubles: pairs waiting on verification, including a login player.
        $this->application($mensDoubles, [$loginPlayers[0], $this->player('Ben Aquino', 'male')], 'pending_verification');
        $this->application($mensDoubles, [$this->player('Ian Gomez', 'male'), $this->player('Ken Ramos', 'male')], 'pending_verification');

        // Mixed Doubles: both slots taken, so the category shows as full.
        foreach (range(1, 2) as $n) {
            $this->approvedEntry($mixedDoubles, [
                $this->player("Rolly Tan $n", 'male'),
                $this->player("Gina Tan $n", 'female'),
            ]);
        }
    }

    /**
     * Concluded single elimination with a decided champion. Completing the
     * final promotes the winners one level, so this is the only category
     * where seeded players' levels move.
     */
    private function seedPastClassic(): void
    {
        $tournament = $this->tournament('Past Classic', 'single_elimination', [
            'date_start' => now()->subDays(30)->toDateString(),
            'date_end' => now()->subDays(28)->toDateString(),
            'registration_start' => now()->subDays(60)->toDateString(),
            'registration_end' => now()->subDays(35)->toDateString(),
        ]);

        if (! $tournament) {
            return;
        }

        $mensSingles = $this->tournamentCategory($tournament, "Men's Singles", 'male', 1, 'Level D', 200, 8);

        foreach (range(1, 4) as $n) {
            $this->approvedEntry($mensSingles, [$this->player("Dado Villar $n", 'male')]);
        }

        app(SingleEliminationScheduler::class)->generate($mensSingles);

        // Round 1 first, then the final: each winner has been advanced into
        // the final before that match is read.
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

    private function seedSpringDraft(): void
    {
        $tournament = $this->tournament('Spring Draft', 'round_robin', [
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
     * Returns null when the tournament was already seeded, so a re-run
     * skips its whole block instead of duplicating entries.
     *
     * @param  array<string, string>  $dates
     */
    private function tournament(string $name, string $format, array $dates, string $status = 'published'): ?Tournament
    {
        $fullName = self::NAME_PREFIX.$name;

        if (Tournament::where('name', $fullName)->exists()) {
            $this->command?->warn("Skipped \"{$fullName}\": already seeded.");

            return null;
        }

        return Tournament::create([
            'name' => $fullName,
            'format' => $format,
            'sport' => 'Badminton',
            'location' => 'OBC Badminton Facility, Ozamiz City',
            'scoring_system' => 'Best of 3 to 21',
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

    private function account(string $email, string $name, string $role): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => $this->password,
            'role' => $role,
            'email_verified_at' => now(),
        ]);

        $this->accounts[] = [$role, $email, $user->wasRecentlyCreated ? 'new' : 'existing (password unchanged)'];

        return $user;
    }

    /**
     * Players are reused by email, mirroring the search-first rule for real
     * registrations: re-running never splits a player's match history.
     */
    private function player(string $name, string $gender, ?string $email = null, ?int $userId = null): Player
    {
        $email ??= Str::slug($name, '.').'@'.self::EMAIL_DOMAIN;

        return Player::firstOrCreate(['email' => $email], [
            'user_id' => $userId,
            'name' => $name,
            'birth_date' => now()->subYears(20 + $this->playerCounter % 15)->subDays($this->playerCounter * 11 % 300)->toDateString(),
            'gender' => $gender,
            'phone_number' => '0917'.str_pad((string) (9100000 + ++$this->playerCounter), 7, '0', STR_PAD_LEFT),
            'address' => 'Ozamiz City',
            'level' => 'Level C',
        ]);
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
            ]);
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

        $this->payment($application, 'verified', 'GCASH-TEST-'.str_pad((string) $application->id, 4, '0', STR_PAD_LEFT));

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
    private function orderedMatches(TournamentCategory $tournamentCategory)
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

        foreach ($gameWinners as $index => $gameWinnerId) {
            MatchGame::create([
                'match_id' => $match->id,
                'game_number' => $index + 1,
                'winner_application_id' => $gameWinnerId,
            ]);
        }

        $match->update(['status' => 'completed', 'winner_application_id' => $winnerId]);
    }

    /**
     * A 1x1 PNG stands in for an uploaded GCash screenshot so "View
     * Attachment" resolves. Written through the public disk, so it lands on
     * the persistent volume or bucket the deployment is configured with.
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
        $tournaments = Tournament::where('name', 'like', self::NAME_PREFIX.'%')->get();

        // Cascades through tournament categories, applications, entries,
        // matches, games and payments; players are restricted until then.
        $tournaments->each->delete();

        $players = Player::where('email', 'like', '%@'.self::EMAIL_DOMAIN)->delete();
        $users = User::where('email', 'like', '%@'.self::EMAIL_DOMAIN)->delete();

        Storage::disk('public')->delete(self::PROOF_PATH);

        $this->command?->info("Purged {$tournaments->count()} tournaments, {$players} players and {$users} users.");
        $this->command?->line('Shared categories created along the way are left in place.');
    }

    private function report(User $admin): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('Test data seeded.');
        $this->command->table(['Role', 'Email', 'State'], $this->accounts);

        if ($this->passwordWasGenerated) {
            $this->command->warn("Generated password for new accounts (shown once): {$this->password}");
        } else {
            $this->command->line('New accounts use the password from TEST_SEED_PASSWORD.');
        }

        $this->command->line("Log in as {$admin->email} to review the admin queue.");
    }
}
