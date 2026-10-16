<?php

use App\Actions\Players\MergeDuplicatePlayers;
use App\Actions\Players\PruneUnusedGuests;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\DismissedPlayerPair;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerLinkRequest;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Players\DuplicatePlayerFinder;
use Livewire\Volt\Volt;

function dupCategory(string $name = 'Dup Cup'): TournamentCategory
{
    $tournament = Tournament::create([
        'name' => $name,
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(5)->toDateString(),
        'date_end' => now()->addDays(6)->toDateString(),
        'registration_start' => now()->subDays(5)->toDateString(),
        'registration_end' => now()->addDays(3)->toDateString(),
        'status' => 'published',
    ]);

    $category = Category::create(['name' => 'Dup Singles '.$name, 'gender' => 'male', 'team_size' => 1]);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 100,
        'max_slots' => 8,
        'status' => 'open',
    ]);
}

function dupEntry(TournamentCategory $tournamentCategory, Player $player, string $status = 'approved'): PlayerApplication
{
    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => $status]);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);

    return $application;
}

function dupPairKeys(): array
{
    return app(DuplicatePlayerFinder::class)->pairs()
        ->map(fn ($pair) => $pair['a']->id.'-'.$pair['b']->id)
        ->all();
}

test('players sharing an email, a name and birthdate, or a phone with a matching name are suggested', function () {
    $sameEmail1 = Player::create(['name' => 'Ann Lee', 'email' => 'ann@example.com']);
    $sameEmail2 = Player::create(['name' => 'A. Lee', 'email' => 'ANN@example.com']);

    $nameDob1 = Player::create(['name' => 'Ben Ong', 'birth_date' => '2000-01-01']);
    $nameDob2 = Player::create(['name' => 'ben m. ong', 'birth_date' => '2000-01-01']);

    $phone1 = Player::create(['name' => 'Cara Diaz', 'phone_number' => '09171112222']);
    $phone2 = Player::create(['name' => 'Cara Reyes', 'phone_number' => '09171112222']);
    $unrelated = Player::create(['name' => 'Dan Smith', 'phone_number' => '09171112222']);

    $keys = dupPairKeys();

    expect($keys)->toContain("{$sameEmail1->id}-{$sameEmail2->id}")
        ->toContain("{$nameDob1->id}-{$nameDob2->id}")
        ->toContain("{$phone1->id}-{$phone2->id}")
        ->not->toContain("{$phone1->id}-{$unrelated->id}")
        ->not->toContain("{$phone2->id}-{$unrelated->id}");
});

test('two players with accounts are never suggested, and dismissed pairs disappear', function () {
    $userA = User::factory()->create(['role' => 'player']);
    $userB = User::factory()->create(['role' => 'player']);
    $a = Player::create(['user_id' => $userA->id, 'name' => 'Eve Cruz', 'email' => 'shared@example.com']);
    $b = Player::create(['user_id' => $userB->id, 'name' => 'Eve Cruz', 'email' => 'shared@example.com']);

    expect(dupPairKeys())->not->toContain("{$a->id}-{$b->id}");

    $guest1 = Player::create(['name' => 'Fay Lim', 'email' => 'fay@example.com']);
    $guest2 = Player::create(['name' => 'Fay Lim', 'email' => 'fay@example.com']);

    expect(dupPairKeys())->toContain("{$guest1->id}-{$guest2->id}");

    DismissedPlayerPair::dismiss($guest2->id, $guest1->id);

    expect(dupPairKeys())->not->toContain("{$guest1->id}-{$guest2->id}");
});

test('merging keeps the record with an account, then the one with more entries, and moves everything over', function () {
    $category = dupCategory();
    $other = dupCategory('Other Cup');

    $user = User::factory()->create(['role' => 'player', 'email' => 'gus@example.com']);
    $account = Player::create(['user_id' => $user->id, 'name' => 'Gus Tan']);
    $guest = Player::create(['name' => 'Gus Tan', 'phone_number' => '09170001111', 'level' => 'Level C', 'birth_date' => '1999-09-09']);

    dupEntry($category, $guest);
    PlayerStatistic::create(['player_id' => $guest->id, 'matches_played' => 3, 'wins' => 2, 'losses' => 1]);
    PlayerStatistic::create(['player_id' => $account->id, 'matches_played' => 1, 'wins' => 1, 'losses' => 0]);
    dupEntry($other, $account);

    $survivor = app(MergeDuplicatePlayers::class)->handle($guest, $account);

    expect($survivor->id)->toBe($account->id)
        ->and($survivor->user_id)->toBe($user->id)
        ->and($survivor->phone_number)->toBe('09170001111')
        ->and($survivor->level)->toBe('Level C')
        ->and(Player::whereKey($guest->id)->exists())->toBeFalse()
        ->and(ApplicationPlayer::where('player_id', $account->id)->count())->toBe(2)
        ->and(PlayerStatistic::find($account->id)->only(['matches_played', 'wins', 'losses']))->toBe(['matches_played' => 4, 'wins' => 3, 'losses' => 1]);
});

test('two guests merge into the one with more entries', function () {
    $a = Player::create(['name' => 'Hal Yu']);
    $b = Player::create(['name' => 'Hal Yu', 'club' => 'Smashers']);
    dupEntry(dupCategory(), $b);

    $survivor = app(MergeDuplicatePlayers::class)->handle($a, $b);

    expect($survivor->id)->toBe($b->id)->and(Player::whereKey($a->id)->exists())->toBeFalse();
});

test('a merge is refused when both have an entry in the same category, or both have accounts', function () {
    $category = dupCategory();
    $a = Player::create(['name' => 'Ivy Go']);
    $b = Player::create(['name' => 'Ivy Go']);
    dupEntry($category, $a);
    dupEntry($category, $b, 'pending_verification');

    expect(fn () => app(MergeDuplicatePlayers::class)->handle($a, $b))->toThrow(InvalidArgumentException::class);
    expect(Player::whereKey([$a->id, $b->id])->count())->toBe(2);

    $c = Player::create(['user_id' => User::factory()->create(['role' => 'player'])->id, 'name' => 'Jo Ng']);
    $d = Player::create(['user_id' => User::factory()->create(['role' => 'player'])->id, 'name' => 'Jo Ng']);

    expect(fn () => app(MergeDuplicatePlayers::class)->handle($c, $d))->toThrow(InvalidArgumentException::class);
});

test('link requests follow the surviving record', function () {
    $user = User::factory()->create(['role' => 'player']);
    $a = Player::create(['name' => 'Kai Sy']);
    $b = Player::create(['name' => 'Kai Sy']);
    dupEntry(dupCategory(), $a);
    $request = PlayerLinkRequest::create(['user_id' => $user->id, 'player_id' => $b->id]);

    $survivor = app(MergeDuplicatePlayers::class)->handle($a, $b);

    expect($survivor->id)->toBe($a->id)->and($request->fresh()->player_id)->toBe($a->id);
});

test('only guests with no account, no entries and no link requests are pruned, and only once old enough', function () {
    $category = dupCategory();

    $unused = Player::create(['name' => 'Old Unused']);
    $unused->forceFill(['created_at' => now()->subDays(10)])->save();

    $fresh = Player::create(['name' => 'Fresh Unused']);

    $withEntry = Player::create(['name' => 'Has Entry']);
    $withEntry->forceFill(['created_at' => now()->subDays(10)])->save();
    dupEntry($category, $withEntry, 'rejected');

    $withAccount = Player::create(['name' => 'Has Account', 'user_id' => User::factory()->create(['role' => 'player'])->id]);
    $withAccount->forceFill(['created_at' => now()->subDays(10)])->save();

    $withRequest = Player::create(['name' => 'Has Request']);
    $withRequest->forceFill(['created_at' => now()->subDays(10)])->save();
    PlayerLinkRequest::create(['user_id' => User::factory()->create(['role' => 'player'])->id, 'player_id' => $withRequest->id]);

    expect(app(PruneUnusedGuests::class)->count())->toBe(1);

    $this->artisan('netplay:prune-guests', ['--dry-run' => true])->assertSuccessful();
    expect(Player::whereKey($unused->id)->exists())->toBeTrue();

    $this->artisan('netplay:prune-guests')->assertSuccessful();

    expect(Player::whereKey($unused->id)->exists())->toBeFalse()
        ->and(Player::whereKey([$fresh->id, $withEntry->id, $withAccount->id, $withRequest->id])->count())->toBe(4);
});

test('the users page lists possible duplicates and lets an admin merge, dismiss and prune', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $a = Player::create(['name' => 'Lou Ray', 'email' => 'lou@example.com']);
    $b = Player::create(['name' => 'Lou Ray', 'email' => 'lou@example.com']);
    dupEntry(dupCategory(), $b);

    $old = Player::create(['name' => 'Stale Guest']);
    $old->forceFill(['created_at' => now()->subDays(30)])->save();

    $component = Volt::test('admin.users.index')
        ->assertSee('possible duplicate')
        ->assertSee('Same email')
        ->assertSee('Delete unused guest records')
        ->call('pruneUnusedGuests');

    expect(Player::whereKey($old->id)->exists())->toBeFalse();

    $component->call('dismissDuplicate', $a->id, $b->id)->assertDontSee('Same email');
    expect(DismissedPlayerPair::count())->toBe(1);

    $c = Player::create(['name' => 'Max Ito', 'email' => 'max@example.com']);
    $d = Player::create(['name' => 'Max Ito', 'email' => 'max@example.com']);

    $component->call('mergeDuplicates', $c->id, $d->id);

    expect(Player::whereKey([$c->id, $d->id])->count())->toBe(1);
});

test('only admins can use the duplicate and prune tools', function () {
    $this->actingAs(User::factory()->create(['role' => 'player']));

    Volt::test('admin.users.index')->assertForbidden();
});
