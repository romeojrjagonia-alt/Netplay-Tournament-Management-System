<?php

use App\Mail\ApplicationReceived;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

function openTournament(array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
        'name' => 'Walk-in Open',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(20),
        'date_end' => now()->addDays(21),
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addDays(10),
        'status' => 'published',
    ], $overrides));
}

function openCategory(Tournament $tournament, int $teamSize = 1, string $gender = 'male'): TournamentCategory
{
    $category = Category::create([
        'name' => $teamSize === 2 ? 'Doubles' : 'Singles',
        'gender' => $gender,
        'team_size' => $teamSize,
    ]);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);
}

function fillGuestDetails($component, TournamentCategory $category, array $overrides = [])
{
    return $component
        ->set('regFirstName', $overrides['first'] ?? 'Juan')
        ->set('regLastName', $overrides['last'] ?? 'Cruz')
        ->set('regBirthDate', '1998-03-01')
        ->set('regGender', 'male')
        ->set('regAddress', 'Purok 5, Ozamiz City')
        ->set('regSkillLevel', 'Beginner')
        ->set('regPhoneNumber', $overrides['phone'] ?? '0917 123 4567')
        ->set('regEmail', $overrides['email'] ?? '')
        ->set('regTournamentCategoryId', $category->id);
}

test('a guest can open the public registration page without signing in', function () {
    $tournament = openTournament();
    openCategory($tournament);

    $this->get(route('tournaments.register', $tournament))
        ->assertOk()
        ->assertSee('Walk-in Open');
});

test('the registration page is not available for tournaments that are not accepting registrations', function () {
    $draft = openTournament(['status' => 'draft']);
    $closed = openTournament(['registration_end' => now()->subDay()]);

    $this->get(route('tournaments.register', $draft))->assertNotFound();
    $this->get(route('tournaments.register', $closed))->assertNotFound();
});

test('a guest registers for singles without an account and gets a reference code', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    $component = Volt::test('tournaments.register', ['tournament' => $tournament]);

    fillGuestDetails($component, $category)
        ->call('nextStep')
        ->assertSet('regStep', 2)
        ->call('submit')
        ->assertHasNoErrors();

    $application = PlayerApplication::firstOrFail();

    expect($application->status)->toBe('pending_verification')
        ->and($application->reference_code)->toMatch('/^NP-[A-Z0-9]{6}$/')
        ->and($application->applicationPlayers)->toHaveCount(1);

    $player = Player::firstOrFail();

    expect($player->user_id)->toBeNull()
        ->and($player->name)->toBe('Juan Cruz')
        ->and($player->phone_number)->toBe('09171234567');

    $component->assertSet('submittedReferenceCode', $application->reference_code);
});

test('a returning guest reuses their player record matched by phone number', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    $existing = Player::create([
        'name' => 'Juan Cruz',
        'phone_number' => '09171234567',
        'level' => 'Level C',
    ]);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['first' => 'Someone', 'last' => 'Else'])
        ->call('nextStep')
        ->call('submit');

    expect(Player::count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Juan Cruz')
        ->and($existing->fresh()->level)->toBe('Level C')
        ->and(ApplicationPlayer::where('player_id', $existing->id)->exists())->toBeTrue();
});

test('a guest cannot enter the same category twice', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    foreach (range(1, 2) as $attempt) {
        $component = fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category)
            ->call('nextStep')
            ->call('submit');
    }

    $component->assertHasErrors('registration');

    expect(PlayerApplication::count())->toBe(1);
});

test('doubles registration collects a partner without an account', function () {
    $tournament = openTournament();
    $category = openCategory($tournament, teamSize: 2);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category)
        ->call('nextStep')
        ->assertSet('regStep', 2)
        ->call('nextStep')
        ->assertHasErrors('pairPlayerId')
        ->call('enterPairManually')
        ->call('nextStep')
        ->assertHasErrors(['pairFirstName', 'pairPhoneNumber', 'pairEmail'])
        ->set('pairFirstName', 'Pedro')
        ->set('pairLastName', 'Santos')
        ->set('pairBirthDate', '1997-05-05')
        ->set('pairGender', 'male')
        ->set('pairAddress', 'Ozamiz City')
        ->set('pairSkillLevel', 'Beginner')
        ->set('pairPhoneNumber', '09181112222')
        ->call('nextStep')
        ->assertSet('regStep', 3)
        ->call('submit')
        ->assertHasNoErrors();

    $application = PlayerApplication::firstOrFail();

    expect($application->applicationPlayers)->toHaveCount(2)
        ->and(Player::pluck('name')->all())->toContain('Pedro Santos');
});

test('a doubles partner cannot be the applicant themselves', function () {
    $tournament = openTournament();
    $category = openCategory($tournament, teamSize: 2);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category)
        ->call('nextStep')
        ->call('enterPairManually')
        ->set('pairFirstName', 'Juan')
        ->set('pairLastName', 'Cruz')
        ->set('pairBirthDate', '1998-03-01')
        ->set('pairGender', 'male')
        ->set('pairAddress', 'Ozamiz City')
        ->set('pairSkillLevel', 'Beginner')
        ->set('pairPhoneNumber', '09171234567')
        ->call('nextStep')
        ->call('submit')
        ->assertHasErrors('registration');

    expect(PlayerApplication::count())->toBe(0);
});

test('a phone number or an email is required', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '', 'email' => ''])
        ->call('nextStep')
        ->assertHasErrors(['regPhoneNumber', 'regEmail']);
});

test('an email alone is enough to register', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '', 'email' => 'juan@example.com'])
        ->call('nextStep')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Player::firstOrFail())
        ->email->toBe('juan@example.com')
        ->phone_number->toBeNull();
});

test('an email-only registration does not collide with an unrelated player without a phone', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    $bystander = Player::create(['name' => 'Bystander']);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '', 'email' => 'new@example.com'])
        ->call('nextStep')
        ->call('submit');

    expect(Player::count())->toBe(2)
        ->and(ApplicationPlayer::where('player_id', $bystander->id)->exists())->toBeFalse();
});

test('a guest is matched to an account holder by the email on the account', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    $user = User::factory()->create(['role' => 'player', 'email' => 'member@example.com']);
    $member = Player::create(['user_id' => $user->id, 'name' => 'Member Player']);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '', 'email' => 'MEMBER@example.com', 'first' => 'Someone', 'last' => 'Else'])
        ->call('nextStep')
        ->call('submit');

    expect(Player::count())->toBe(1)
        ->and(ApplicationPlayer::where('player_id', $member->id)->exists())->toBeTrue()
        ->and($member->fresh()->name)->toBe('Member Player');
});

test('a doubles partner is found by name search and only the name is exposed', function () {
    $tournament = openTournament();
    $category = openCategory($tournament, teamSize: 2);

    $partner = Player::create([
        'name' => 'Pedro Santos',
        'phone_number' => '09181112222',
        'email' => 'pedro@example.com',
        'address' => 'Secret Street 9',
        'level' => 'Level B',
    ]);

    $component = fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category)
        ->call('nextStep')
        ->set('pairSearch', 'Pedro')
        ->assertSee('Pedro Santos')
        ->assertDontSee('09181112222')
        ->assertDontSee('pedro@example.com')
        ->assertDontSee('Secret Street')
        ->call('selectPairPlayer', $partner->id)
        ->assertSet('pairPlayerId', $partner->id)
        ->call('nextStep')
        ->assertSet('regStep', 3)
        ->call('submit')
        ->assertHasNoErrors();

    $application = PlayerApplication::firstOrFail();

    expect($application->applicationPlayers->pluck('player_id')->all())->toContain($partner->id)
        ->and(Player::count())->toBe(2);

    expect($application->applicationPlayers->firstWhere('player_id', $partner->id)->level_at_apply)->toBe('Level B');
});

test('a doubles partner entered by email is matched to an existing account holder', function () {
    $tournament = openTournament();
    $category = openCategory($tournament, teamSize: 2);

    $user = User::factory()->create(['role' => 'player', 'email' => 'pedro@example.com']);
    $partner = Player::create(['user_id' => $user->id, 'name' => 'Pedro Santos']);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category)
        ->call('nextStep')
        ->call('enterPairManually')
        ->set('pairFirstName', 'Pete')
        ->set('pairLastName', 'Santos')
        ->set('pairBirthDate', '1997-05-05')
        ->set('pairGender', 'male')
        ->set('pairAddress', 'Ozamiz City')
        ->set('pairSkillLevel', 'Beginner')
        ->set('pairEmail', 'pedro@example.com')
        ->call('nextStep')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Player::count())->toBe(2)
        ->and(ApplicationPlayer::where('player_id', $partner->id)->exists())->toBeTrue();
});

test('the reference code is emailed when an email is given', function () {
    Mail::fake();

    $tournament = openTournament();
    $category = openCategory($tournament);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['email' => 'juan@example.com'])
        ->call('nextStep')
        ->call('submit');

    Mail::assertSent(ApplicationReceived::class, fn ($mail) => $mail->hasTo('juan@example.com'));
});

test('a signed-in player keeps their own player record when registering', function () {
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $tournament = openTournament();
    $category = openCategory($tournament);

    $this->actingAs($user);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '09999999999'])
        ->call('nextStep')
        ->call('submit');

    expect(Player::count())->toBe(1)
        ->and(ApplicationPlayer::where('player_id', $player->id)->exists())->toBeTrue();
});

test('an admin registers a walk-in without the walk-in being tied to the admin account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Player::create(['user_id' => $admin->id, 'name' => 'Admin Person']);

    $tournament = openTournament();
    $category = openCategory($tournament);

    $this->actingAs($admin);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '09170000001'])
        ->assertSet('regFirstName', 'Juan')
        ->call('nextStep')
        ->call('submit')
        ->assertHasNoErrors();

    $walkIn = Player::where('phone_number', '09170000001')->firstOrFail();

    expect($walkIn->user_id)->toBeNull()
        ->and(Player::count())->toBe(2)
        ->and(ApplicationPlayer::where('player_id', $walkIn->id)->exists())->toBeTrue();
});

test('an admin is not throttled when registering many walk-ins', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $tournament = openTournament();
    $category = openCategory($tournament);

    foreach (range(1, 12) as $i) {
        fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, [
            'phone' => '0917000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'first' => "Walkin$i",
        ])
            ->call('nextStep')
            ->call('submit')
            ->assertHasNoErrors();
    }

    expect(PlayerApplication::count())->toBe(12);
});

test('a category refuses applications once its slots are taken', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);
    $category->update(['max_slots' => 2]);

    foreach (['0917000001', '0917000002'] as $phone) {
        fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => $phone, 'first' => 'P'.$phone])
            ->call('nextStep')
            ->call('submit')
            ->assertHasNoErrors();
    }

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '0917000003', 'first' => 'Late'])
        ->call('nextStep')
        ->call('submit')
        ->assertHasErrors('registration')
        ->assertDispatched('system-message', variant: 'danger');

    expect(PlayerApplication::count())->toBe(2);
});

test('a rejected application frees its slot', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);
    $category->update(['max_slots' => 1]);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '0917000001'])
        ->call('nextStep')
        ->call('submit');

    PlayerApplication::firstOrFail()->update(['status' => 'rejected']);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '0917000002', 'first' => 'Next'])
        ->call('nextStep')
        ->call('submit')
        ->assertHasNoErrors();

    expect(PlayerApplication::where('status', '!=', 'rejected')->count())->toBe(1);
});
