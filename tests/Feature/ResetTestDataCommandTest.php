<?php

use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

it('refuses to run without --force when not interactive', function () {
    User::factory()->create(['email' => 'keep@example.com']);

    $this->artisan('netplay:reset-test-data', ['--no-interaction' => true])->assertFailed();

    expect(User::where('email', 'keep@example.com')->exists())->toBeTrue();
});

it('wipes every record and seeds test accounts with password123', function () {
    $user = User::factory()->create(['email' => 'old@example.com', 'role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => 'Old Player']);
    Tournament::create([
        'name' => 'Old Event', 'format' => 'single_elimination', 'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21', 'date_start' => now()->addDays(2), 'date_end' => now()->addDays(3),
        'registration_start' => now()->subDay(), 'registration_end' => now()->addDay(), 'status' => 'published',
    ]);

    $this->artisan('netplay:reset-test-data', ['--force' => true])->assertSuccessful();

    expect(User::where('email', 'old@example.com')->exists())->toBeFalse()
        ->and(Player::where('name', 'Old Player')->exists())->toBeFalse()
        ->and(Tournament::where('name', 'Old Event')->exists())->toBeFalse()
        ->and(Tournament::where('name', 'like', '[TEST] %')->count())->toBe(5);

    foreach (['admin', 'umpire1', 'umpire2', 'player1', 'player2', 'player3'] as $account) {
        $seeded = User::where('email', "{$account}@seed.netplay.test")->first();

        expect($seeded)->not->toBeNull()
            ->and(Hash::check('password123', $seeded->password))->toBeTrue();
    }
});
