<?php

use App\Models\Matches;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerLinkRequest;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Players\DuplicatePlayerFinder;
use App\Support\Scheduling\SingleEliminationScheduler;
use Database\Seeders\LiveDemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function demoCategory(string $tournament, string $category): TournamentCategory
{
    return TournamentCategory::whereHas('tournament', fn ($query) => $query->where('name', $tournament))
        ->whereHas('category', fn ($query) => $query->where('name', $category))
        ->firstOrFail();
}

it('seeds every demo tournament, account and the admin', function () {
    $this->seed(LiveDemoSeeder::class);

    expect(Tournament::whereIn('name', LiveDemoSeeder::TOURNAMENTS)->count())->toBe(6)
        ->and(User::where('email', 'Admin@Netplay.com')->where('role', 'admin')->exists())->toBeTrue()
        ->and(User::where('email', 'like', '%@'.LiveDemoSeeder::EMAIL_DOMAIN)->where('role', 'umpire')->count())->toBe(2)
        ->and(User::where('email', 'like', '%@'.LiveDemoSeeder::EMAIL_DOMAIN)->where('role', 'player')->count())->toBe(4);

    $umpire = User::where('email', 'ramon.umpire@'.LiveDemoSeeder::EMAIL_DOMAIN)->firstOrFail();

    expect(Hash::check(LiveDemoSeeder::DEFAULT_PASSWORD, $umpire->password))->toBeTrue()
        ->and($umpire->hasVerifiedEmail())->toBeTrue()
        ->and(User::where('email', 'dana.ortiz@'.LiveDemoSeeder::EMAIL_DOMAIN)->firstOrFail()->hasVerifiedEmail())->toBeFalse();
});

it('covers the tournament states an admin needs to see', function () {
    $this->seed(LiveDemoSeeder::class);

    $byName = fn (string $name) => Tournament::where('name', $name)->firstOrFail();

    expect($byName('OBC Summer Open')->is_published)->toBeTrue()
        ->and(Tournament::acceptingRegistrations()->where('name', 'OBC Summer Open')->exists())->toBeTrue()
        ->and($byName('Winter Draft')->status)->toBe('draft')
        ->and($byName('Smash Knockout Classic')->is_concluded)->toBeTrue()
        ->and($byName('Round Robin Showdown')->format)->toBe('round_robin')
        ->and($byName('Pickleball Fun Cup')->sport)->toBe('Pickleball')
        ->and($byName('Pickleball Fun Cup')->scoring_system)->toBe('Pickleball Best of 3 to 11');
});

it('leaves Men\'s Singles undrawn, ranked by statistics, with every review state in the queue', function () {
    $this->seed(LiveDemoSeeder::class);

    $mensSingles = demoCategory('OBC Summer Open', "Men's Singles");

    expect($mensSingles->hasGeneratedDraw())->toBeFalse()
        ->and($mensSingles->playerApplications()->where('status', 'approved')->count())->toBe(8)
        ->and($mensSingles->playerApplications()->where('status', 'pending_verification')->count())->toBe(1)
        ->and($mensSingles->playerApplications()->where('status', 'payment_pending')->count())->toBe(2)
        ->and($mensSingles->playerApplications()->where('status', 'rejected')->whereNotNull('rejection_reason')->count())->toBe(1)
        ->and(PaymentDetail::where('payment_status', 'pending')->count())->toBe(1)
        ->and(PaymentDetail::where('payment_status', 'rejected')->count())->toBe(1);

    // Approved weakest-first, yet the strongest player is seeded on top.
    $slots = app(SingleEliminationScheduler::class)->arrangement($mensSingles);
    $top = PlayerApplication::with('applicationPlayers.player')->find($slots[0]);
    $bottomOfPair = PlayerApplication::with('applicationPlayers.player')->find($slots[1]);

    expect($top->applicationPlayers->first()->player->name)->toBe('Alex Ace')
        ->and($bottomOfPair->applicationPlayers->first()->player->name)->toBe('Wally Newbie');
});

it('draws Women\'s Singles with byes and fills Mixed Doubles', function () {
    $this->seed(LiveDemoSeeder::class);

    $womens = demoCategory('OBC Summer Open', "Women's Singles");
    $mixed = demoCategory('OBC Summer Open', 'Mixed Doubles');

    expect($womens->hasGeneratedDraw())->toBeTrue()
        ->and($womens->matches()->where('status', 'completed')->count())->toBeGreaterThan(2)
        ->and($mixed->playerApplications()->where('status', 'approved')->count())->toBe($mixed->max_slots);
});

it('gives the umpire completed, in-progress and upcoming matches', function () {
    $this->seed(LiveDemoSeeder::class);

    $umpire = User::where('email', 'ramon.umpire@'.LiveDemoSeeder::EMAIL_DOMAIN)->firstOrFail();
    $mine = Matches::where('assigned_umpire_id', $umpire->id);

    expect((clone $mine)->where('status', 'completed')->count())->toBeGreaterThan(0)
        ->and((clone $mine)->where('status', 'in_progress')->count())->toBeGreaterThan(0)
        ->and((clone $mine)->where('status', 'scheduled')->count())->toBeGreaterThan(0)
        ->and((clone $mine)->whereNull('schedule_notified_at')->whereNotNull('scheduled_at')->count())->toBeGreaterThan(0);

    $completed = (clone $mine)->where('status', 'completed')->first();

    expect($completed->games()->count())->toBeGreaterThanOrEqual(2)
        ->and($completed->line_judge_1_name)->not->toBeNull();
});

it('records pickleball scores to 11', function () {
    $this->seed(LiveDemoSeeder::class);

    $played = demoCategory('Pickleball Fun Cup', "Men's Doubles")->matches()->where('status', 'completed')->firstOrFail();

    expect($played->usesPickleballRules())->toBeTrue()
        ->and(collect($played->gameScores())->filter(fn (array $game) => $game['A'] > 0 || $game['B'] > 0)->every(fn (array $game) => max($game['A'], $game['B']) === 11))->toBeTrue();
});

it('shows the player every match card state', function () {
    $this->seed(LiveDemoSeeder::class);

    $alex = Player::where('email', 'alex.rivera@'.LiveDemoSeeder::EMAIL_DOMAIN)->firstOrFail();
    $matches = Matches::whereHas('matchApplications.application.applicationPlayers', fn ($query) => $query->where('player_id', $alex->id))
        ->whereHas('tournamentCategory.tournament', fn ($query) => $query->where('name', 'Player Spotlight Cup'))
        ->get();

    expect($matches->where('status', 'completed')->count())->toBe(2)
        ->and($matches->where('status', 'in_progress')->count())->toBe(1)
        ->and($matches->where('status', 'scheduled')->count())->toBe(1);
});

it('prepares the Users page with a link request and likely duplicates', function () {
    $this->seed(LiveDemoSeeder::class);

    expect(PlayerLinkRequest::pending()->count())->toBe(1);

    $duplicates = app(DuplicatePlayerFinder::class)->pairs()
        ->filter(fn (array $pair) => str_contains($pair['a']->name, 'Maria') && str_contains($pair['b']->name, 'Maria'));

    expect($duplicates)->toHaveCount(1);
});

it('uses valid phone numbers for every player', function () {
    $this->seed(LiveDemoSeeder::class);

    expect(Player::whereNotNull('phone_number')->pluck('phone_number')->every(fn (string $number) => preg_match('/^09\d{9}$/', $number) === 1))->toBeTrue();
});

it('can be run again without duplicating anything', function () {
    $this->seed(LiveDemoSeeder::class);

    $counts = fn () => [
        Tournament::count(),
        User::count(),
        Player::count(),
        PlayerApplication::count(),
        Matches::count(),
        PlayerLinkRequest::count(),
    ];

    $before = $counts();

    $this->seed(LiveDemoSeeder::class);

    expect($counts())->toBe($before);
});

it('does not touch the password of an account that already exists', function () {
    User::factory()->create([
        'email' => 'ramon.umpire@'.LiveDemoSeeder::EMAIL_DOMAIN,
        'role' => 'umpire',
        'password' => 'MyOwnPass123',
    ]);

    $this->seed(LiveDemoSeeder::class);

    $umpire = User::where('email', 'ramon.umpire@'.LiveDemoSeeder::EMAIL_DOMAIN)->firstOrFail();

    expect(Hash::check('MyOwnPass123', $umpire->password))->toBeTrue();
});

it('can be removed again', function () {
    $this->seed(LiveDemoSeeder::class);

    putenv('DEMO_SEED_PURGE=true');
    $_ENV['DEMO_SEED_PURGE'] = 'true';

    try {
        $this->seed(LiveDemoSeeder::class);
    } finally {
        putenv('DEMO_SEED_PURGE');
        unset($_ENV['DEMO_SEED_PURGE']);
    }

    expect(Tournament::whereIn('name', LiveDemoSeeder::TOURNAMENTS)->count())->toBe(0)
        ->and(User::where('email', 'like', '%@'.LiveDemoSeeder::EMAIL_DOMAIN)->count())->toBe(0)
        ->and(Player::where('email', 'like', '%@'.LiveDemoSeeder::EMAIL_DOMAIN)->count())->toBe(0)
        ->and(User::where('email', 'Admin@Netplay.com')->exists())->toBeTrue();
});
