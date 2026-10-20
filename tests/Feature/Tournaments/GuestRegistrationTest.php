<?php

use App\Mail\ApplicationReceived;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(fn () => Storage::fake('public'));

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
        ->set('regTournamentCategoryId', $category->id)
        ->set('ownDocumentType', 'national_id')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->set('pairDocumentType', 'passport')
        ->set('pairDocument', UploadedFile::fake()->create('partner-id.jpg', 100, 'image/jpeg'));
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

test('a returning guest reuses their player record matched by phone number and name', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    $existing = Player::create([
        'name' => 'Juan Cruz',
        'phone_number' => '09171234567',
        'level' => 'Level C',
    ]);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['first' => 'JUAN', 'last' => 'cruz'])
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

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '', 'email' => 'MEMBER@example.com', 'first' => 'Member', 'last' => 'Player'])
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
        ->set('pairFirstName', 'Pedro')
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
            'first' => 'Walkin'.chr(64 + $i),
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

    foreach (['0917000001' => 'Alpha', '0917000002' => 'Bravo'] as $phone => $first) {
        fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => $phone, 'first' => $first])
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

test('the guest form stores each player\'s ID document with the entry', function () {
    $category = openCategory(openTournament(), teamSize: 2, gender: 'mixed');

    $component = Volt::test('tournaments.register', ['tournament' => $category->tournament]);

    fillGuestDetails($component, $category)
        ->call('nextStep')
        ->call('enterPairManually')
        ->set('pairFirstName', 'Maria')
        ->set('pairLastName', 'Santos')
        ->set('pairBirthDate', '1999-05-05')
        ->set('pairGender', 'female')
        ->set('pairAddress', 'Ozamiz City')
        ->set('pairSkillLevel', 'Beginner')
        ->set('pairPhoneNumber', '0918 765 4321')
        ->call('nextStep')
        ->call('submit')
        ->assertHasNoErrors();

    $documents = ApplicationPlayer::query()->orderBy('id')->get();

    expect($documents)->toHaveCount(2)
        ->and($documents[0]->id_document_type)->toBe('national_id')
        ->and($documents[1]->id_document_type)->toBe('passport');

    foreach ($documents as $document) {
        Storage::disk('public')->assertExists($document->id_document_path);
    }
});

test('the guest form requires an ID document for the player and the partner', function () {
    $category = openCategory(openTournament());

    $component = Volt::test('tournaments.register', ['tournament' => $category->tournament]);

    fillGuestDetails($component, $category)
        ->call('nextStep')
        ->set('ownDocument', null)
        ->set('ownDocumentType', '')
        ->call('submit')
        ->assertHasErrors(['ownDocumentType', 'ownDocument']);

    expect(PlayerApplication::count())->toBe(0);
});

test('a different name on a shared phone number is a separate player and never exposes the owner', function () {
    $tournament = openTournament();
    $category = openCategory($tournament, gender: 'mixed');

    $owner = Player::create(['name' => 'Jane S. Doe', 'phone_number' => '09171234567']);
    $application = PlayerApplication::create(['tournament_category_id' => $category->id, 'status' => 'approved']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $owner->id, 'level_at_apply' => 'Beginner']);

    $component = fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['first' => 'Maria', 'last' => 'Reyes'])
        ->call('nextStep')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Player::count())->toBe(2)
        ->and(ApplicationPlayer::where('player_id', $owner->id)->count())->toBe(1)
        ->and(Player::where('name', 'Maria Reyes')->exists())->toBeTrue()
        ->and($component->get('submittedReferenceCode'))->not->toBeNull();
});

test('the already-entered error does not reveal the player name', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    foreach (range(1, 2) as $attempt) {
        $component = fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category)
            ->call('nextStep')
            ->call('submit');
    }

    $component->assertDispatched('system-message', message: 'You already have a pending application for this tournament.');
});

test('the same name and birthdate is the same person even with a new phone or email', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    $existing = Player::create([
        'name' => 'Juan D. Cruz',
        'birth_date' => '1998-03-01',
        'phone_number' => '09170000000',
        'email' => 'old@example.com',
    ]);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '09999999999', 'email' => 'new@example.com'])
        ->call('nextStep')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Player::count())->toBe(1)
        ->and(ApplicationPlayer::where('player_id', $existing->id)->exists())->toBeTrue()
        ->and($existing->fresh()->phone_number)->toBe('09170000000');
});

test('the same name with a different birthdate and phone is a different person', function () {
    $tournament = openTournament();
    $category = openCategory($tournament);

    Player::create(['name' => 'Juan Cruz', 'birth_date' => '1985-07-07', 'phone_number' => '09170000000']);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['phone' => '09999999999'])
        ->call('nextStep')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Player::count())->toBe(2);
});

test('guests are nudged to sign in, and signed-in users and admins are not', function () {
    $tournament = openTournament();
    openCategory($tournament);

    Volt::test('tournaments.register', ['tournament' => $tournament])
        ->assertSee('Have an account? Sign in');

    expect(session('url.intended'))->toBe(url()->current());

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Volt::test('tournaments.register', ['tournament' => $tournament])
        ->assertDontSee('Have an account? Sign in');
});

test('the confirmation screen and the reference email invite guests to sign in', function () {
    Mail::fake();

    $tournament = openTournament();
    $category = openCategory($tournament);

    fillGuestDetails(Volt::test('tournaments.register', ['tournament' => $tournament]), $category, ['email' => 'juan@example.com'])
        ->call('nextStep')
        ->call('submit')
        ->assertSee('with the same email so this registration stays with your account');

    Mail::assertSent(ApplicationReceived::class, fn ($mail) => str_contains($mail->render(), 'Sign in to NetPlay'));
});
