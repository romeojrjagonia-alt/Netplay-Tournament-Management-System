<?php

use App\Models\Matches;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\DeployTestDataSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    putenv('TEST_SEED_PASSWORD=seeded-secret-123');
});

afterEach(function () {
    putenv('TEST_SEED_PASSWORD');
    putenv('TEST_SEED_PURGE');
});

it('seeds tournaments, accounts and match data', function () {
    $this->seed(DeployTestDataSeeder::class);

    expect(Tournament::where('name', 'like', '[TEST] %')->count())->toBe(5);
    expect(Tournament::where('name', '[TEST] Spring Draft')->value('status'))->toBe('draft');

    $admin = User::where('email', 'admin@seed.netplay.test')->first();

    expect($admin->role)->toBe('admin')
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('seeded-secret-123', $admin->password))->toBeTrue();

    expect(User::where('role', 'umpire')->count())->toBe(2);
    expect(Matches::where('status', 'completed')->count())->toBeGreaterThan(0);
    expect(Matches::where('status', 'in_progress')->count())->toBe(2);
    expect(PlayerApplication::whereIn('status', ['pending_verification', 'payment_pending', 'rejected'])->count())->toBeGreaterThan(0);
    expect(PaymentDetail::where('payment_status', 'pending')->count())->toBe(1);

    Storage::disk('public')->assertExists('payment-proofs/seed-test-receipt.png');
});

it('leaves the knockout men\'s singles undrawn so generation can be tested', function () {
    $this->seed(DeployTestDataSeeder::class);

    $category = Tournament::where('name', '[TEST] Knockout Cup')->first()
        ->tournamentCategories()
        ->whereHas('category', fn ($query) => $query->where('name', "Men's Singles"))
        ->first();

    expect($category->hasGeneratedDraw())->toBeFalse()
        ->and($category->playerApplications()->where('status', 'approved')->count())->toBe(5);
});

it('is safe to run twice', function () {
    $this->seed(DeployTestDataSeeder::class);

    $counts = fn () => [
        Tournament::count(),
        Player::count(),
        PlayerApplication::count(),
        Matches::count(),
        User::count(),
    ];

    $before = $counts();

    $this->seed(DeployTestDataSeeder::class);

    expect($counts())->toBe($before);
});

it('purges only what it created', function () {
    $realPlayer = Player::create(['name' => 'Real Walk-in', 'email' => 'real@example.com']);
    $realUser = User::factory()->create();

    $this->seed(DeployTestDataSeeder::class);

    putenv('TEST_SEED_PURGE=true');
    $this->seed(DeployTestDataSeeder::class);

    expect(Tournament::count())->toBe(0)
        ->and(Matches::count())->toBe(0)
        ->and(PlayerApplication::count())->toBe(0)
        ->and(User::whereKey($realUser->id)->exists())->toBeTrue()
        ->and(Player::whereKey($realPlayer->id)->exists())->toBeTrue()
        ->and(Player::count())->toBe(1)
        ->and(User::count())->toBe(1);

    Storage::disk('public')->assertMissing('payment-proofs/seed-test-receipt.png');
});
