<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\ClearSeededDataSeeder;
use Database\Seeders\LiveDemoSeeder;
use Database\Seeders\StatisticSeedingDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    confirmClear();

    // The seeder insists on the seeded admin existing.
    $this->seed(AdminSeeder::class);
});

afterEach(function () {
    unconfirmClear();
});

/**
 * Stands in for answering "yes" to the confirmation question.
 */
function confirmClear(): void
{
    putenv('CLEAR_DATA_CONFIRMED=true');
    $_ENV['CLEAR_DATA_CONFIRMED'] = 'true';
}

function unconfirmClear(): void
{
    putenv('CLEAR_DATA_CONFIRMED');
    unset($_ENV['CLEAR_DATA_CONFIRMED']);
}

/**
 * A real event with one approved entry for the given player.
 */
function realEvent(string $name, ?Player $entrant = null, string $proof = 'payment-proofs/real-receipt.png'): TournamentCategory
{
    $tournament = Tournament::create([
        'name' => $name,
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(20)->toDateString(),
        'date_end' => now()->addDays(21)->toDateString(),
        'registration_start' => now()->subDay()->toDateString(),
        'registration_end' => now()->addDays(10)->toDateString(),
        'status' => 'published',
    ]);

    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => Category::create(['name' => "Men's Singles ".$name, 'gender' => 'male', 'team_size' => 1])->id,
        'registration_fee' => 150,
        'max_slots' => 32,
        'status' => 'open',
    ]);

    if ($entrant) {
        $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
        ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $entrant->id, 'level_at_apply' => 'Beginner']);
        PaymentDetail::create([
            'application_id' => $application->id,
            'date_of_payment_made' => now(),
            'reference_number' => 'REAL-1',
            'payment_status' => 'verified',
            'proof_of_payment' => $proof,
        ]);
        Storage::disk('public')->put($proof, 'real receipt');
    }

    return $tournamentCategory;
}

it('removes the demo data and leaves real data alone', function () {
    $this->seed(LiveDemoSeeder::class);

    $realUser = User::factory()->create(['role' => 'player', 'email' => 'real.person@gmail.com', 'name' => 'Real Person']);
    $realPlayer = $realUser->ensurePlayer();
    $walkIn = Player::create(['name' => 'Real Walk-in', 'phone_number' => '09175550000']);
    $realEvent = realEvent('Barangay Cup', $realPlayer);
    realEvent('Neighbourhood Open', $walkIn, 'payment-proofs/walkin-receipt.png');

    $this->seed(ClearSeededDataSeeder::class);

    expect(Tournament::whereIn('name', LiveDemoSeeder::TOURNAMENTS)->count())->toBe(0)
        ->and(User::where('email', 'like', '%@'.LiveDemoSeeder::EMAIL_DOMAIN)->count())->toBe(0)
        ->and(Player::where('email', 'like', '%@'.LiveDemoSeeder::EMAIL_DOMAIN)->count())->toBe(0)
        ->and(Tournament::pluck('name')->sort()->values()->all())->toBe(['Barangay Cup', 'Neighbourhood Open'])
        ->and(User::pluck('email')->sort()->values()->all())->toBe(['Admin@Netplay.com', 'real.person@gmail.com'])
        ->and(Player::pluck('name')->sort()->values()->all())->toBe(['Real Person', 'Real Walk-in'])
        ->and(PlayerApplication::count())->toBe(2)
        ->and(PaymentDetail::count())->toBe(2);

    Storage::disk('public')->assertExists('payment-proofs/real-receipt.png');
    Storage::disk('public')->assertExists('payment-proofs/walkin-receipt.png');
    Storage::disk('public')->assertMissing(LiveDemoSeeder::PROOF_PATH);
});

it('keeps the seeded admin, the category templates and any real sessions', function () {
    $this->seed(LiveDemoSeeder::class);
    $templates = Category::count();

    $admin = User::where('email', AdminSeeder::EMAIL)->firstOrFail();
    $demoUser = User::where('email', 'ramon.umpire@'.LiveDemoSeeder::EMAIL_DOMAIN)->firstOrFail();
    $realUser = User::factory()->create(['role' => 'umpire']);

    foreach ([['a', $admin->id], ['b', $demoUser->id], ['c', $realUser->id], ['d', null]] as [$id, $userId]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
    }

    DB::table('password_reset_tokens')->insert([
        ['email' => $demoUser->email, 'token' => 'x', 'created_at' => now()],
        ['email' => $realUser->email, 'token' => 'y', 'created_at' => now()],
    ]);

    $this->seed(ClearSeededDataSeeder::class);

    expect(User::where('email', AdminSeeder::EMAIL)->exists())->toBeTrue()
        ->and(Category::count())->toBe($templates)
        ->and(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(['a', 'c', 'd'])
        ->and(DB::table('password_reset_tokens')->pluck('email')->all())->toBe([$realUser->email]);
});

it('also clears the statistic seeding demo, including its walk-in players', function () {
    $this->seed(StatisticSeedingDemoSeeder::class);

    expect(Tournament::where('name', StatisticSeedingDemoSeeder::TOURNAMENT)->exists())->toBeTrue()
        ->and(Player::where('name', 'Alex Ace')->exists())->toBeTrue();

    $realEvent = realEvent('Real Cup');

    $this->seed(ClearSeededDataSeeder::class);

    expect(Tournament::pluck('name')->all())->toBe(['Real Cup'])
        ->and(Player::count())->toBe(0);
});

it('does not touch an event just because an old seeder used its name', function () {
    realEvent('Inato Tournament');
    realEvent('Summer Slam');

    $this->seed(ClearSeededDataSeeder::class);

    expect(Tournament::pluck('name')->sort()->values()->all())->toBe(['Inato Tournament', 'Summer Slam']);
});

it('removes the older test-seeder data too', function () {
    realEvent('[TEST] Knockout Cup');
    realEvent('Round Robin Regen Demo');
    User::factory()->create(['email' => 'admin@seed.netplay.test', 'role' => 'admin']);
    User::factory()->create(['email' => 'schedule-umpire@example.com', 'role' => 'umpire']);
    $real = User::factory()->create(['email' => 'someone@example.com', 'role' => 'player']);

    $this->seed(ClearSeededDataSeeder::class);

    expect(Tournament::count())->toBe(0)
        ->and(User::pluck('email')->sort()->values()->all())->toBe(['Admin@Netplay.com', 'someone@example.com']);
});

it('only removes a real person\'s registration in a demo tournament, never the person', function () {
    $this->seed(LiveDemoSeeder::class);

    $realUser = User::factory()->create(['role' => 'player', 'email' => 'real.person@gmail.com']);
    $realPlayer = $realUser->ensurePlayer();

    $demoCategory = TournamentCategory::whereHas('tournament', fn ($query) => $query->where('name', 'OBC Summer Open'))->firstOrFail();
    $application = PlayerApplication::create(['tournament_category_id' => $demoCategory->id, 'status' => 'pending_verification']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $realPlayer->id, 'level_at_apply' => 'Beginner']);

    $this->seed(ClearSeededDataSeeder::class);

    expect(User::where('email', 'real.person@gmail.com')->exists())->toBeTrue()
        ->and(Player::whereKey($realPlayer->id)->exists())->toBeTrue()
        ->and(PlayerApplication::whereKey($application->id)->exists())->toBeFalse();
});

it('keeps a demo-domain player who has a place in a real tournament', function () {
    $this->seed(LiveDemoSeeder::class);

    $demoPlayer = Player::where('email', 'alex.rivera@'.LiveDemoSeeder::EMAIL_DOMAIN)->firstOrFail();
    realEvent('Real Cup', $demoPlayer);

    $this->seed(ClearSeededDataSeeder::class);

    expect(Player::whereKey($demoPlayer->id)->exists())->toBeTrue()
        ->and($demoPlayer->fresh()->user_id)->toBeNull()
        ->and(Tournament::where('name', 'Real Cup')->exists())->toBeTrue();
});

it('never deletes a file that something real still points at', function () {
    $this->seed(LiveDemoSeeder::class);

    // A real record sharing the demo's placeholder image.
    $walkIn = Player::create(['name' => 'Shared File Walk-in']);
    realEvent('Real Cup', $walkIn, LiveDemoSeeder::PROOF_PATH);

    $this->seed(ClearSeededDataSeeder::class);

    Storage::disk('public')->assertExists(LiveDemoSeeder::PROOF_PATH);
});

it('refuses to run, and deletes nothing, when the seeded admin does not exist', function () {
    $this->seed(LiveDemoSeeder::class);
    User::where('email', AdminSeeder::EMAIL)->delete();
    $before = [Tournament::count(), User::count()];

    $this->seed(ClearSeededDataSeeder::class);

    expect([Tournament::count(), User::count()])->toBe($before);
});

it('refuses to run on production unless confirmed in advance', function () {
    $this->seed(LiveDemoSeeder::class);
    $before = [Tournament::count(), User::count()];

    unconfirmClear();
    $this->app->detectEnvironment(fn () => 'production');

    // --force gets past Laravel's own production prompt; the seeder still refuses.
    $this->artisan('db:seed', ['--class' => ClearSeededDataSeeder::class, '--force' => true])
        ->expectsOutputToContain('Nothing was deleted')
        ->assertSuccessful();

    expect([Tournament::count(), User::count()])->toBe($before);

    confirmClear();

    $this->artisan('db:seed', ['--class' => ClearSeededDataSeeder::class, '--force' => true])
        ->expectsOutputToContain('Seeded data cleared.')
        ->assertSuccessful();

    expect(Tournament::whereIn('name', LiveDemoSeeder::TOURNAMENTS)->count())->toBe(0);
});

it('asks before deleting anything and stops when the answer is no', function () {
    $this->seed(LiveDemoSeeder::class);
    unconfirmClear();

    $this->artisan('db:seed', ['--class' => ClearSeededDataSeeder::class])
        ->expectsConfirmation('This permanently deletes the seeded demo and test data (real data is left alone). Continue?', 'no')
        ->expectsOutputToContain('Nothing was deleted')
        ->assertSuccessful();

    expect(Tournament::count())->toBeGreaterThan(0)
        ->and(User::count())->toBeGreaterThan(1);
});

it('deletes once the question is answered yes', function () {
    $this->seed(LiveDemoSeeder::class);
    unconfirmClear();

    $this->artisan('db:seed', ['--class' => ClearSeededDataSeeder::class])
        ->expectsConfirmation('This permanently deletes the seeded demo and test data (real data is left alone). Continue?', 'yes')
        ->expectsOutputToContain('Seeded data cleared.')
        ->assertSuccessful();

    expect(Tournament::count())->toBe(0)
        ->and(User::pluck('email')->all())->toBe(['Admin@Netplay.com']);
});

it('can be run again, and the demo can be seeded again afterwards', function () {
    $this->seed(LiveDemoSeeder::class);
    $this->seed(ClearSeededDataSeeder::class);
    $this->seed(ClearSeededDataSeeder::class);

    expect(Tournament::count())->toBe(0)
        ->and(Matches::count())->toBe(0);

    $this->seed(LiveDemoSeeder::class);

    expect(Tournament::whereIn('name', LiveDemoSeeder::TOURNAMENTS)->count())->toBe(6);
});
