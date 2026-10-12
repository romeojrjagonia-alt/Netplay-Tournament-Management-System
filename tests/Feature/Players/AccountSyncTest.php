<?php

use App\Actions\Players\MergePlayerRecords;
use App\Mail\PlayerLinkDecided;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerLinkRequest;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

function syncCategory(string $name = 'Sync Cup'): TournamentCategory
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

    $category = Category::create(['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1]);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 100,
        'max_slots' => 8,
        'status' => 'open',
    ]);
}

function syncGuest(TournamentCategory $tournamentCategory, array $overrides = []): Player
{
    $guest = Player::create(array_merge([
        'name' => 'Guest Gary',
        'email' => 'gary@example.com',
        'gender' => 'male',
        'level' => 'Level G',
        'phone_number' => '09170000001',
    ], $overrides));

    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $guest->id, 'level_at_apply' => 'Level G']);

    return $guest;
}

function syncAccount(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'name' => 'Gary Account',
        'email' => 'gary@example.com',
        'role' => 'player',
    ], $overrides));

    Player::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'photo_path' => 'player-photos/gary.jpg']);

    return $user;
}

test('a verified account is offered guest records registered under its email', function () {
    $guest = syncGuest(syncCategory());
    $user = syncAccount();

    $this->actingAs($user);

    Volt::test('player.link-prompt')
        ->assertSee('Guest Gary')
        ->assertSee('Sync Cup')
        ->assertDontSee('09170000001');
});

test('an unverified account is not offered anything', function () {
    syncGuest(syncCategory());
    $user = syncAccount(['email_verified_at' => null]);

    $this->actingAs($user);

    Volt::test('player.link-prompt')->assertDontSee('Guest Gary');
});

test('only records with the same email are offered, matched case-insensitively', function () {
    $category = syncCategory();
    syncGuest($category, ['name' => 'Other Person', 'email' => 'someone@else.com']);
    syncGuest($category, ['name' => 'Upper Case Gary', 'email' => 'GARY@Example.com']);

    $this->actingAs(syncAccount());

    Volt::test('player.link-prompt')
        ->assertSee('Upper Case Gary')
        ->assertDontSee('Other Person');
});

test('confirming creates a pending request and nothing is merged yet', function () {
    $guest = syncGuest(syncCategory());
    $user = syncAccount();

    $this->actingAs($user);

    Volt::test('player.link-prompt')
        ->set('selected', [$guest->id])
        ->call('requestLink')
        ->assertSee('awaiting organizer review');

    expect(PlayerLinkRequest::where(['user_id' => $user->id, 'player_id' => $guest->id, 'status' => 'pending'])->exists())->toBeTrue()
        ->and($guest->fresh()->user_id)->toBeNull();
});

test('a player cannot claim a record that was not offered to them', function () {
    $stranger = syncGuest(syncCategory(), ['name' => 'Stranger', 'email' => 'stranger@example.com']);
    $user = syncAccount();

    $this->actingAs($user);

    Volt::test('player.link-prompt')
        ->set('selected', [$stranger->id])
        ->call('requestLink')
        ->assertHasErrors('selected');

    expect(PlayerLinkRequest::count())->toBe(0);
});

test('not me is remembered so the record is never offered again', function () {
    $guest = syncGuest(syncCategory());
    $user = syncAccount();

    $this->actingAs($user);

    Volt::test('player.link-prompt')->call('decline');

    expect(PlayerLinkRequest::where('player_id', $guest->id)->value('status'))->toBe('declined');

    Volt::test('player.link-prompt')->assertDontSee('Guest Gary');
});

test('approving merges the sign-up player into the guest record and moves history', function () {
    Mail::fake();

    $category = syncCategory();
    $guest = syncGuest($category, ['level' => 'Level F', 'address' => null]);
    PlayerStatistic::create(['player_id' => $guest->id, 'matches_played' => 4, 'wins' => 3, 'losses' => 1]);

    $user = syncAccount();
    $own = $user->player;
    $own->update(['address' => 'Purok 5, Ozamiz City']);
    PlayerStatistic::create(['player_id' => $own->id, 'matches_played' => 1, 'wins' => 0, 'losses' => 1]);

    $request = PlayerLinkRequest::create(['user_id' => $user->id, 'player_id' => $guest->id]);

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Volt::test('admin.users.index')->call('approveLinkRequest', $request->id);

    $survivor = $user->fresh()->player;

    expect($survivor->id)->toBe($guest->id)
        ->and($survivor->name)->toBe('Guest Gary')
        ->and($survivor->level)->toBe('Level F')
        ->and($survivor->address)->toBe('Purok 5, Ozamiz City')
        ->and($survivor->photo_path)->toBe('player-photos/gary.jpg')
        ->and(Player::whereKey($own->id)->exists())->toBeFalse()
        ->and(ApplicationPlayer::where('player_id', $guest->id)->count())->toBe(1)
        ->and(PlayerStatistic::find($guest->id)->only(['matches_played', 'wins', 'losses']))->toBe(['matches_played' => 5, 'wins' => 3, 'losses' => 2])
        ->and($request->fresh()->status)->toBe('approved');

    Mail::assertQueued(PlayerLinkDecided::class, fn ($mail) => $mail->hasTo('gary@example.com'));
});

test('a merge is refused when both records have an active entry in the same category', function () {
    $category = syncCategory();
    $guest = syncGuest($category);
    $user = syncAccount();

    $application = PlayerApplication::create(['tournament_category_id' => $category->id, 'status' => 'pending_verification']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $user->player->id, 'level_at_apply' => 'Beginner']);

    expect(fn () => app(MergePlayerRecords::class)->handle($guest, $user))->toThrow(InvalidArgumentException::class);

    expect($user->fresh()->player->id)->not->toBe($guest->id)
        ->and(Player::whereKey($guest->id)->value('user_id'))->toBeNull();
});

test('rejecting keeps the records separate, stores the reason and emails the player', function () {
    Mail::fake();

    $guest = syncGuest(syncCategory());
    $user = syncAccount();
    $request = PlayerLinkRequest::create(['user_id' => $user->id, 'player_id' => $guest->id]);

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Volt::test('admin.users.index')
        ->call('openRejectLinkRequest', $request->id)
        ->set('linkRejectionReason', 'Not the same person')
        ->call('rejectLinkRequest');

    expect($request->fresh()->status)->toBe('rejected')
        ->and($request->fresh()->rejection_reason)->toBe('Not the same person')
        ->and($guest->fresh()->user_id)->toBeNull();

    Mail::assertQueued(PlayerLinkDecided::class);

    // Final for that record: it is never offered to the player again.
    $this->actingAs($user);
    Volt::test('player.link-prompt')->assertDontSee('Guest Gary');
});

test('an admin can link a guest record without a request, and only admins can', function () {
    Mail::fake();

    $guest = syncGuest(syncCategory(), ['email' => null, 'name' => 'No Email Nina']);
    $user = syncAccount(['email' => 'nina@example.com']);

    $this->actingAs(User::factory()->create(['role' => 'player']));
    Volt::test('admin.users.index')->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Volt::test('admin.users.index')
        ->call('openLinkModal', $user->id)
        ->set('playerSearch', 'Nina')
        ->assertSee('No Email Nina')
        ->call('linkPlayer', $guest->id);

    expect($user->fresh()->player->id)->toBe($guest->id)
        ->and(PlayerLinkRequest::where(['user_id' => $user->id, 'player_id' => $guest->id, 'status' => 'approved'])->exists())->toBeTrue();
});
