<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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
                'date_start' => '2026-09-01',
                'date_end' => '2026-09-30',
                'time' => '9:00 AM - 5:00 PM',
                'specific_days' => 'SAT, SUN',
                'registration_start' => '2026-06-01',
                'registration_end' => '2026-09-20',
                'status' => 'published',
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
                'registration_end' => '2026-09-15',
                'status' => 'published',
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
                'status' => 'draft',
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

        $this->seedDemoAdminQueue($inatoSingles, $inatoMixedDoubles, $barangayCup);
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

    /**
     * Seed a handful of applications sitting in the admin's review queue —
     * two awaiting eligibility verification, two awaiting payment
     * verification (with a demo receipt) — so the admin dashboard has
     * something real to show before staff start processing live entries.
     */
    private function seedDemoAdminQueue(
        TournamentCategory $inatoSingles,
        TournamentCategory $inatoMixedDoubles,
        Tournament $barangayCup
    ): void {
        $mensDoubles = Category::firstOrCreate(
            ['name' => "Men's Doubles", 'gender' => 'male', 'team_size' => 2],
            ['level' => 'Level B']
        );

        $barangayMensDoubles = TournamentCategory::firstOrCreate(
            ['tournament_id' => $barangayCup->id, 'category_id' => $mensDoubles->id],
            ['registration_fee' => 300, 'max_slots' => 16, 'status' => 'open']
        );

        $marcus = $this->demoPlayer('Marcus Villanueva', '1998-03-22', 'male', '09171234567', 'Aguada, Ozamiz City', 'Intermediate');
        $ella = $this->demoPlayer('Ella Bautista', '2001-07-09', 'female', '09228765432', 'Tinago, Ozamiz City', 'Beginner');
        $ramon = $this->demoPlayer('Ramon Dela Cruz', '1995-11-02', 'male', '09051234567', 'Manuel S. Enverga, Ozamiz City', 'Advanced');
        $cristina = $this->demoPlayer('Cristina Reyes', '1997-05-18', 'female', '09981234567', 'Aguada, Ozamiz City', 'Advanced');
        $paolo = $this->demoPlayer('Paolo Santos', '1996-02-14', 'male', '09161234567', 'Aguada, Ozamiz City', 'Intermediate');
        $miguel = $this->demoPlayer('Miguel Torres', '1994-08-30', 'male', '09051112233', 'Tinago, Ozamiz City', 'Intermediate');

        // Pending Approvals: awaiting eligibility verification, no payment yet.
        $this->createDemoApplication($inatoSingles, [$marcus], 'pending_verification');
        $this->createDemoApplication($inatoMixedDoubles, [$ramon, $cristina], 'pending_verification');

        // Pending Payments: verification already confirmed, receipt uploaded and awaiting staff review.
        $barangayWomensDoubles = $this->createDemoApplication(
            TournamentCategory::firstOrCreate(
                ['tournament_id' => $barangayCup->id, 'category_id' => Category::firstOrCreate(
                    ['name' => "Women's Singles", 'gender' => 'female', 'team_size' => 1]
                )->id],
                ['registration_fee' => 200, 'max_slots' => 24, 'status' => 'open']
            ),
            [$ella],
            'payment_pending'
        );
        $this->createDemoPayment($barangayWomensDoubles, now()->subDays(2), '0987654321');

        $mensDoublesApplication = $this->createDemoApplication($barangayMensDoubles, [$paolo, $miguel], 'payment_pending');
        $this->createDemoPayment($mensDoublesApplication, now()->subDay(), '1122334455');
    }

    private function demoPlayer(string $name, string $birthDate, string $gender, string $phone, string $address, string $level): Player
    {
        return Player::firstOrCreate(['name' => $name], [
            'birth_date' => $birthDate,
            'gender' => $gender,
            'phone_number' => $phone,
            'address' => $address,
            'level' => $level,
        ]);
    }

    /**
     * Idempotent per player group: re-running the seeder reuses the
     * existing application for the same first player instead of duplicating it.
     *
     * @param  array<int, Player>  $players
     */
    private function createDemoApplication(TournamentCategory $tournamentCategory, array $players, string $status): PlayerApplication
    {
        $existing = PlayerApplication::where('tournament_category_id', $tournamentCategory->id)
            ->whereHas('applicationPlayers', fn ($query) => $query->where('player_id', $players[0]->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        $application = PlayerApplication::create([
            'tournament_category_id' => $tournamentCategory->id,
            'status' => $status,
        ]);

        foreach ($players as $player) {
            ApplicationPlayer::create([
                'application_id' => $application->id,
                'player_id' => $player->id,
                'level_at_apply' => $player->level,
            ]);
        }

        return $application;
    }

    /**
     * A tiny transparent PNG stands in for the uploaded GCash screenshot,
     * so "View Attachment" resolves to a real, viewable file.
     */
    private function createDemoPayment(PlayerApplication $application, Carbon $paidAt, string $referenceNumber): void
    {
        if ($application->paymentDetails()->exists()) {
            return;
        }

        $proofPath = 'payment-proofs/demo-receipt-'.$application->id.'.png';

        if (! Storage::disk('public')->exists($proofPath)) {
            Storage::disk('public')->put($proofPath, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
            ));
        }

        PaymentDetail::create([
            'application_id' => $application->id,
            'date_of_payment_made' => $paidAt,
            'reference_number' => $referenceNumber,
            'payment_status' => 'pending',
            'proof_of_payment' => $proofPath,
        ]);
    }
}
