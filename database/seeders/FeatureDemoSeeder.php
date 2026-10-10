<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Scheduling\RoundRobinScheduler;
use Illuminate\Database\Seeder;

class FeatureDemoSeeder extends Seeder
{
    /**
     * Demo data for hand-testing the admin, player and umpire flows.
     *
     * Accounts (all verified, password: "password"):
     * - demo-admin@netplay.test
     * - demo-umpire@netplay.test, demo-umpire2@netplay.test
     * - demo-player@netplay.test (has applications in several states)
     *
     * "Demo Round Robin Cup" (registration open, round robin):
     * - Men's Singles: 10 approved entries, NO schedule yet. Generate it, then
     *   set time/court/umpire per match and use "Email players".
     * - Women's Singles: 4 approved entries WITH a schedule, times, courts and
     *   an umpire assigned, ready to score. Also 1 pending-verification and
     *   1 payment-pending application (with a proof waiting) for the admin.
     * - Men's Doubles: 3 approved pairs and 1 pair pending verification.
     * - Mixed Doubles: 2 slots, both taken, to see the "full" behaviour.
     *
     * "Demo Knockout Open" (registration open, single elimination):
     * - Men's Singles: 5 approved entries (3 byes), NO bracket yet.
     *
     * Safe to re-run: it does nothing if the demo tournaments already exist.
     */
    public function run(): void
    {
        $this->user('demo-admin@netplay.test', 'Demo Admin', 'admin');
        $umpire = $this->user('demo-umpire@netplay.test', 'Demo Umpire', 'umpire');
        $this->user('demo-umpire2@netplay.test', 'Second Demo Umpire', 'umpire');
        $demoUser = $this->user('demo-player@netplay.test', 'Demo Player', 'player');

        if (Tournament::where('name', 'Demo Round Robin Cup')->exists()) {
            return;
        }

        $demoPlayer = Player::firstOrCreate(['user_id' => $demoUser->id], [
            'name' => 'Demo Player',
            'birth_date' => '1996-04-12',
            'gender' => 'male',
            'phone_number' => '09170009000',
            'email' => $demoUser->email,
            'address' => 'Ozamiz City',
            'level' => 'Level C',
        ]);

        $roundRobin = $this->tournament('Demo Round Robin Cup', 'round_robin', 21);
        $knockout = $this->tournament('Demo Knockout Open', 'single_elimination', 28);

        $mensSingles = $this->category($roundRobin, "Men's Singles", 'male', 1, 250, 16);
        $womensSingles = $this->category($roundRobin, "Women's Singles", 'female', 1, 250, 16);
        $mensDoubles = $this->category($roundRobin, "Men's Doubles", 'male', 2, 400, 16);
        $mixedDoubles = $this->category($roundRobin, 'Mixed Doubles', 'mixed', 2, 400, 2);
        $knockoutSingles = $this->category($knockout, "Men's Singles", 'male', 1, 200, 16);

        // Men's Singles: 10 approved entries, the demo player is the first.
        $this->approvedEntry($mensSingles, [$demoPlayer]);

        foreach (range(1, 9) as $n) {
            $this->approvedEntry($mensSingles, [$this->player("Demo Man $n", 'male', $n)]);
        }

        // Women's Singles: a ready-to-score schedule.
        foreach (range(1, 4) as $n) {
            $this->approvedEntry($womensSingles, [$this->player("Demo Woman $n", 'female', 100 + $n)]);
        }

        app(RoundRobinScheduler::class)->generate($womensSingles);

        $slot = now()->addDays(21)->setTime(9, 0);

        Matches::where('tournament_category_id', $womensSingles->id)->orderBy('round')->orderBy('position')->get()
            ->each(function (Matches $match, int $index) use ($slot, $umpire): void {
                $match->update([
                    'scheduled_at' => $slot->copy()->addMinutes($index * 45),
                    'court' => (string) (($index % 2) + 1),
                    'assigned_umpire_id' => $umpire->id,
                ]);
            });

        // Applications the admin still has to act on.
        $this->application($womensSingles, [$this->player('Demo Woman Pending', 'female', 110)], 'pending_verification');

        $awaitingPayment = $this->application($womensSingles, [$this->player('Demo Woman Paying', 'female', 111)], 'payment_pending');
        PaymentDetail::create([
            'application_id' => $awaitingPayment->id,
            'date_of_payment_made' => now()->toDateString(),
            'reference_number' => 'GCASH-DEMO-0001',
            'payment_status' => 'pending',
            'proof_of_payment' => 'payment-proofs/demo-proof.png',
        ]);

        // Men's Doubles.
        foreach (range(1, 3) as $n) {
            $this->approvedEntry($mensDoubles, [
                $this->player("Demo Doubles $n A", 'male', 200 + $n * 2),
                $this->player("Demo Doubles $n B", 'male', 201 + $n * 2),
            ]);
        }

        $this->application($mensDoubles, [
            $demoPlayer,
            $this->player('Demo Doubles Partner', 'male', 210),
        ], 'pending_verification');

        // Mixed Doubles: full.
        foreach (range(1, 2) as $n) {
            $this->approvedEntry($mixedDoubles, [
                $this->player("Demo Mixed $n Man", 'male', 300 + $n * 2),
                $this->player("Demo Mixed $n Woman", 'female', 301 + $n * 2),
            ]);
        }

        // Knockout: 5 entries means a bracket of 8 with 3 byes.
        foreach (range(1, 4) as $n) {
            $this->approvedEntry($knockoutSingles, [$this->player("Demo Knockout $n", 'male', 400 + $n)]);
        }

        $this->application($knockoutSingles, [$demoPlayer], 'payment_pending');
    }

    private function user(string $email, string $name, string $role): User
    {
        return User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function tournament(string $name, string $format, int $startsInDays): Tournament
    {
        return Tournament::create([
            'name' => $name,
            'format' => $format,
            'sport' => 'Badminton',
            'location' => 'OBC Badminton Facility, Ozamiz City',
            'scoring_system' => 'Best of 3 to 21',
            'date_start' => now()->addDays($startsInDays)->toDateString(),
            'date_end' => now()->addDays($startsInDays + 2)->toDateString(),
            'time' => '9:00 AM - 5:00 PM',
            'specific_days' => 'Sat, Sun',
            'registration_start' => now()->subDays(7)->toDateString(),
            'registration_end' => now()->addDays($startsInDays - 3)->toDateString(),
            'status' => 'published',
            'tiebreaker_rules' => ['head_to_head', 'point_differential'],
        ]);
    }

    private function category(Tournament $tournament, string $name, string $gender, int $teamSize, int $fee, int $slots): TournamentCategory
    {
        $category = Category::firstOrCreate(
            ['name' => $name, 'gender' => $gender, 'team_size' => $teamSize, 'level' => 'Level C'],
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

    private function player(string $name, string $gender, int $number): Player
    {
        return Player::firstOrCreate(['name' => $name], [
            'birth_date' => '1997-06-15',
            'gender' => $gender,
            'phone_number' => '0917'.str_pad((string) $number, 7, '0', STR_PAD_LEFT),
            'email' => 'demo.player'.$number.'@example.test',
            'address' => 'Ozamiz City',
            'level' => 'Level C',
        ]);
    }

    /**
     * @param  list<Player>  $players
     */
    private function application(TournamentCategory $tournamentCategory, array $players, string $status): PlayerApplication
    {
        $application = PlayerApplication::create([
            'tournament_category_id' => $tournamentCategory->id,
            'status' => $status,
        ]);

        foreach ($players as $player) {
            ApplicationPlayer::create([
                'application_id' => $application->id,
                'player_id' => $player->id,
                'level_at_apply' => $player->level ?? 'Level C',
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

        PaymentDetail::create([
            'application_id' => $application->id,
            'date_of_payment_made' => now()->toDateString(),
            'reference_number' => 'GCASH-DEMO-'.str_pad((string) $application->id, 4, '0', STR_PAD_LEFT),
            'payment_status' => 'verified',
            'proof_of_payment' => 'payment-proofs/demo-proof.png',
        ]);

        return $application;
    }
}
