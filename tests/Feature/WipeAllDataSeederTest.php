<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerLinkRequest;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LiveDemoSeeder;
use Database\Seeders\WipeAllDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('public');
    confirmWipe();
});

afterEach(function () {
    unconfirmWipe();
});

/**
 * Stands in for answering "yes" to the confirmation question.
 */
function confirmWipe(): void
{
    putenv('WIPE_ALL_CONFIRMED=true');
    $_ENV['WIPE_ALL_CONFIRMED'] = 'true';
}

function unconfirmWipe(): void
{
    putenv('WIPE_ALL_CONFIRMED');
    unset($_ENV['WIPE_ALL_CONFIRMED']);
}

it('removes everything except the seeded admin account', function () {
    $this->seed(LiveDemoSeeder::class);

    expect(Tournament::count())->toBeGreaterThan(0)
        ->and(User::count())->toBeGreaterThan(1);

    $this->seed(WipeAllDataSeeder::class);

    expect(Tournament::count())->toBe(0)
        ->and(PlayerApplication::count())->toBe(0)
        ->and(ApplicationPlayer::count())->toBe(0)
        ->and(PaymentDetail::count())->toBe(0)
        ->and(Matches::count())->toBe(0)
        ->and(DB::table('match_games')->count())->toBe(0)
        ->and(DB::table('rallies')->count())->toBe(0)
        ->and(Player::count())->toBe(0)
        ->and(PlayerStatistic::count())->toBe(0)
        ->and(PlayerLinkRequest::count())->toBe(0)
        ->and(User::pluck('email')->all())->toBe(['Admin@Netplay.com']);
});

it('leaves the admin able to sign in and the category templates in place', function () {
    $this->seed(LiveDemoSeeder::class);
    $templates = Category::count();

    expect($templates)->toBeGreaterThan(0);

    $this->seed(WipeAllDataSeeder::class);

    $admin = User::where('email', AdminSeeder::EMAIL)->firstOrFail();

    expect($admin->role)->toBe('admin')
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check(AdminSeeder::PASSWORD, $admin->password))->toBeTrue()
        ->and(Category::count())->toBe($templates);

    Volt::test('auth.login')
        ->set('email', AdminSeeder::EMAIL)
        ->set('password', AdminSeeder::PASSWORD)
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($admin);
});

it('deletes the uploaded files of what it removes but keeps the admin\'s own picture', function () {
    $this->seed(LiveDemoSeeder::class);

    $admin = User::where('email', AdminSeeder::EMAIL)->firstOrFail();
    Storage::disk('public')->put('staff-photos/admin.jpg', 'admin picture');
    $admin->update(['photo_path' => 'staff-photos/admin.jpg']);

    $other = User::factory()->create(['role' => 'umpire', 'photo_path' => 'staff-photos/umpire.jpg']);
    Storage::disk('public')->put('staff-photos/umpire.jpg', 'umpire picture');

    Storage::disk('public')->assertExists(LiveDemoSeeder::PROOF_PATH);

    $this->seed(WipeAllDataSeeder::class);

    Storage::disk('public')->assertMissing(LiveDemoSeeder::PROOF_PATH);
    Storage::disk('public')->assertMissing('staff-photos/umpire.jpg');
    Storage::disk('public')->assertExists('staff-photos/admin.jpg');

    expect($admin->fresh()->photo_path)->toBe('staff-photos/admin.jpg');
});

it('keeps the admin\'s own player record but not its history', function () {
    $this->seed(LiveDemoSeeder::class);

    $admin = User::where('email', AdminSeeder::EMAIL)->firstOrFail();
    $own = Player::create(['user_id' => $admin->id, 'name' => 'Admin Person']);
    PlayerStatistic::create(['player_id' => $own->id, 'matches_played' => 4, 'wins' => 3, 'losses' => 1]);

    $this->seed(WipeAllDataSeeder::class);

    expect(Player::pluck('id')->all())->toBe([$own->id])
        ->and(PlayerStatistic::count())->toBe(0);
});

it('clears sessions and reset tokens of the removed accounts', function () {
    $this->seed(LiveDemoSeeder::class);

    $admin = User::where('email', AdminSeeder::EMAIL)->firstOrFail();
    $other = User::where('email', 'ramon.umpire@'.LiveDemoSeeder::EMAIL_DOMAIN)->firstOrFail();

    foreach ([['a', $admin->id], ['b', $other->id], ['c', null]] as [$id, $userId]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
    }

    DB::table('password_reset_tokens')->insert([
        ['email' => $admin->email, 'token' => 'x', 'created_at' => now()],
        ['email' => $other->email, 'token' => 'y', 'created_at' => now()],
    ]);

    $this->seed(WipeAllDataSeeder::class);

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['a'])
        ->and(DB::table('password_reset_tokens')->pluck('email')->all())->toBe([$admin->email]);
});

it('refuses to run, and deletes nothing, when the seeded admin does not exist', function () {
    User::factory()->create(['role' => 'player']);
    Tournament::create([
        'name' => 'Keep Me',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(5)->toDateString(),
        'date_end' => now()->addDays(6)->toDateString(),
        'registration_start' => now()->toDateString(),
        'registration_end' => now()->addDays(3)->toDateString(),
        'status' => 'published',
    ]);

    $this->seed(WipeAllDataSeeder::class);

    expect(Tournament::count())->toBe(1)
        ->and(User::count())->toBe(1);
});

it('refuses to run on production unless confirmed in advance', function () {
    $this->seed(LiveDemoSeeder::class);
    $before = [Tournament::count(), User::count()];

    unconfirmWipe();
    $this->app->detectEnvironment(fn () => 'production');

    // --force gets past Laravel's own production prompt; the seeder still refuses.
    $this->artisan('db:seed', ['--class' => WipeAllDataSeeder::class, '--force' => true])
        ->expectsOutputToContain('Nothing was deleted')
        ->assertSuccessful();

    expect([Tournament::count(), User::count()])->toBe($before);

    confirmWipe();

    $this->artisan('db:seed', ['--class' => WipeAllDataSeeder::class, '--force' => true])
        ->expectsOutputToContain('All data cleared.')
        ->assertSuccessful();

    expect(Tournament::count())->toBe(0)
        ->and(User::count())->toBe(1);
});

it('can be run again on an already empty system', function () {
    $this->seed(LiveDemoSeeder::class);
    $this->seed(WipeAllDataSeeder::class);
    $this->seed(WipeAllDataSeeder::class);

    expect(User::pluck('email')->all())->toBe(['Admin@Netplay.com'])
        ->and(Tournament::count())->toBe(0);
});

it('lets the demo data be seeded again afterwards', function () {
    $this->seed(LiveDemoSeeder::class);
    $this->seed(WipeAllDataSeeder::class);
    $this->seed(LiveDemoSeeder::class);

    expect(Tournament::whereIn('name', LiveDemoSeeder::TOURNAMENTS)->count())->toBe(6)
        ->and(User::where('email', AdminSeeder::EMAIL)->count())->toBe(1);
});

it('asks before deleting anything and stops when the answer is no', function () {
    $this->seed(LiveDemoSeeder::class);
    unconfirmWipe();

    $this->artisan('db:seed', ['--class' => WipeAllDataSeeder::class])
        ->expectsConfirmation('This permanently deletes EVERY tournament, player and account, real or seeded, except Admin@Netplay.com. Continue?', 'no')
        ->expectsOutputToContain('Nothing was deleted')
        ->assertSuccessful();

    expect(Tournament::count())->toBeGreaterThan(0)
        ->and(User::count())->toBeGreaterThan(1);
});

it('deletes once the question is answered yes', function () {
    $this->seed(LiveDemoSeeder::class);
    unconfirmWipe();

    $this->artisan('db:seed', ['--class' => WipeAllDataSeeder::class])
        ->expectsConfirmation('This permanently deletes EVERY tournament, player and account, real or seeded, except Admin@Netplay.com. Continue?', 'yes')
        ->expectsOutputToContain('All data cleared.')
        ->assertSuccessful();

    expect(Tournament::count())->toBe(0)
        ->and(User::pluck('email')->all())->toBe(['Admin@Netplay.com']);
});
