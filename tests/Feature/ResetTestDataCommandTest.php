<?php

use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('refuses to run without --force when not interactive', function () {
    User::factory()->create(['email' => 'keep@example.com']);

    $this->artisan('netplay:reset-test-data', ['--no-interaction' => true])->assertFailed();

    expect(User::where('email', 'keep@example.com')->exists())->toBeTrue();
});

it('wipes every record and creates only the test accounts with password123', function () {
    $user = User::factory()->create(['email' => 'old@example.com', 'role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => 'Old Player']);
    Tournament::create([
        'name' => 'Old Event', 'format' => 'single_elimination', 'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21', 'date_start' => now()->addDays(2), 'date_end' => now()->addDays(3),
        'registration_start' => now()->subDay(), 'registration_end' => now()->addDay(), 'status' => 'published',
    ]);

    $this->artisan('netplay:reset-test-data', ['--force' => true])->assertSuccessful();

    expect(User::where('email', 'old@example.com')->exists())->toBeFalse()
        ->and(Tournament::count())->toBe(0)
        ->and(PlayerApplication::count())->toBe(0)
        ->and(Matches::count())->toBe(0)
        ->and(User::count())->toBe(6)
        ->and(Player::count())->toBe(3);

    foreach (['admin', 'umpire1', 'umpire2', 'player1', 'player2', 'player3'] as $account) {
        $seeded = User::where('email', "{$account}@seed.netplay.test")->first();

        expect($seeded)->not->toBeNull()
            ->and($seeded->hasVerifiedEmail())->toBeTrue()
            ->and(Hash::check('password123', $seeded->password))->toBeTrue();
    }
});
