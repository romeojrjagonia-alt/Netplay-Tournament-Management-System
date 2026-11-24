<?php

use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('seeds a demonstration dataset covering the whole workflow', function () {
    $this->seed(DemoDataSeeder::class);

    expect(Tournament::pluck('name')->all())->toContain(
        DemoDataSeeder::LIVE_EVENT,
        DemoDataSeeder::REGISTRATION_EVENT,
        DemoDataSeeder::DRAFT_EVENT,
    );

    expect(User::where('role', 'umpire')->count())->toBe(2)
        ->and(User::where('role', 'admin')->exists())->toBeTrue()
        ->and(Player::whereNull('user_id')->count())->toBeGreaterThan(Player::whereNotNull('user_id')->count());

    expect(PlayerApplication::distinct()->pluck('status')->sort()->values()->all())
        ->toBe(['approved', 'payment_pending', 'pending_verification', 'rejected']);
});

it('plays the live event so winners advance and scores follow the rules', function () {
    $this->seed(DemoDataSeeder::class);

    $live = Tournament::where('name', DemoDataSeeder::LIVE_EVENT)->firstOrFail();
    $singles = $live->tournamentCategories()->whereHas('category', fn ($q) => $q->where('team_size', 1))->firstOrFail();

    $matches = Matches::where('tournament_category_id', $singles->id)->get();

    expect($matches)->toHaveCount(7)
        ->and($matches->where('round', 1)->every(fn ($m) => $m->status === 'completed'))->toBeTrue()
        ->and($matches->where('status', 'in_progress'))->toHaveCount(1);

    $semiFinal = $matches->firstWhere('status', 'in_progress');
    expect($semiFinal->matchApplications()->count())->toBe(2);

    $completed = $matches->firstWhere(fn ($m) => $m->round === 1 && $m->position === 1);
    $completed->load('games.rallies', 'matchApplications.application.applicationPlayers');
    $sideMap = $completed->playerSideMap();

    $completed->games->each(function (MatchGame $game) use ($sideMap) {
        expect($game->winningSide($sideMap))->not->toBeNull();
    });

    expect(Player::where('name', 'Alex Reyes')->first()->statistic->matches_played)->toBeGreaterThan(20);
});

it('can be run twice without duplicating events', function () {
    $this->seed(DemoDataSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(Tournament::count())->toBe(3);
});
