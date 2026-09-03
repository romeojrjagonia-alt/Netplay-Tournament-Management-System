<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;

function registrationTournament(array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
        'name' => 'Inato Tournament',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-07-01',
        'date_end' => '2026-07-31',
        'time' => '9:00 AM - 5:00 PM',
        'specific_days' => 'SAT, SUN',
        'registration_start' => '2026-06-01',
        'registration_end' => '2026-12-31',
        'status' => 'published',
    ], $overrides));
}

function registrationCategory(Tournament $tournament, array $categoryOverrides = [], array $tournamentCategoryOverrides = []): TournamentCategory
{
    $category = Category::create(array_merge([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
    ], $categoryOverrides));

    return TournamentCategory::create(array_merge([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 150,
        'max_slots' => 32,
        'status' => 'open',
    ], $tournamentCategoryOverrides));
}

function fillProfileStep(Testable $component, int $tournamentCategoryId, array $overrides = []): Testable
{
    $fields = array_merge([
        'regFirstName' => 'Juan',
        'regLastName' => 'Cruz',
        'regMiddleInitial' => 'D',
        'regBirthDate' => now()->subYears(25)->format('Y-m-d'),
        'regGender' => 'male',
        'regAddress' => 'Purok 5, Ozamiz City',
        'regSkillLevel' => 'Beginner',
        'regClub' => 'OBC Badminton Club',
        'regEmail' => 'juancruz@example.com',
        'regPhoneNumber' => '09171234567',
        'regTournamentCategoryId' => $tournamentCategoryId,
    ], $overrides);

    foreach ($fields as $property => $value) {
        $component->set($property, $value);
    }

    return $component;
}

test('a male player only sees eligible categories for a singles registration', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    $tournament = registrationTournament();
    registrationCategory($tournament, ['name' => "Men's Singles", 'gender' => 'male']);
    registrationCategory($tournament, ['name' => "Women's Singles", 'gender' => 'female']);
    $mixed = registrationCategory($tournament, ['name' => 'Mixed Doubles', 'gender' => 'mixed', 'team_size' => 2]);

    $this->actingAs($user);

    $component = Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->call('startRegistration')
        ->set('regGender', 'male');

    $eligibleNames = $component->eligibleCategories->pluck('category.name');

    expect($eligibleNames)->toContain("Men's Singles", 'Mixed Doubles')
        ->not->toContain("Women's Singles");

    // Attempting to submit an ineligible category id fails validation.
    fillProfileStep($component, $mixed->id + 999999)
        ->call('registrationNextStep')
        ->assertHasErrors(['regTournamentCategoryId']);
});

test('a player can submit a singles application with one document', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    $tournament = registrationTournament();
    $tournamentCategory = registrationCategory($tournament);

    $this->actingAs($user);

    $component = Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->call('startRegistration');

    fillProfileStep($component, $tournamentCategory->id)
        ->call('registrationNextStep')
        ->assertHasNoErrors();

    // Singles never presents a pair step.
    expect($component->get('regStep'))->toBe(2);
    expect($component->isDoublesRegistration)->toBeFalse();

    $component->set('ownDocumentType', 'national_id')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('submitRegistration')
        ->assertHasNoErrors();

    $application = PlayerApplication::where('tournament_category_id', $tournamentCategory->id)->sole();
    expect($application->status)->toBe('pending_verification');

    $applicationPlayers = ApplicationPlayer::where('application_id', $application->id)->get();
    expect($applicationPlayers)->toHaveCount(1);
    expect($applicationPlayers->first()->id_document_type)->toBe('national_id');
    expect($applicationPlayers->first()->id_document_path)->not->toBeNull();

    Storage::disk('public')->assertExists($applicationPlayers->first()->id_document_path);
});

test('doubles registration with a pair found by search creates two application players', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    $pairUser = User::factory()->create(['role' => 'player']);
    $pairPlayer = Player::create(['user_id' => $pairUser->id, 'name' => 'Robert Lee', 'gender' => 'male']);

    $tournament = registrationTournament();
    $tournamentCategory = registrationCategory($tournament, ['name' => "Men's Doubles", 'gender' => 'male', 'team_size' => 2]);

    $this->actingAs($user);

    $component = Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->call('startRegistration');

    fillProfileStep($component, $tournamentCategory->id, ['regGender' => 'male'])
        ->set('pairHasAccount', 'yes')
        ->call('registrationNextStep')
        ->assertHasNoErrors();

    expect($component->isDoublesRegistration)->toBeTrue();
    expect($component->pairViaSearch)->toBeTrue();

    $component->set('pairSearch', 'Robert')
        ->call('selectPairPlayer', $pairPlayer->id)
        ->call('registrationNextStep')
        ->assertHasNoErrors();

    $component->set('ownDocumentType', 'national_id')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->set('pairDocumentType', 'passport')
        ->set('pairDocument', UploadedFile::fake()->create('pair-id.jpg', 100, 'image/jpeg'))
        ->call('submitRegistration')
        ->assertHasNoErrors();

    $application = PlayerApplication::where('tournament_category_id', $tournamentCategory->id)->sole();
    $applicationPlayers = ApplicationPlayer::where('application_id', $application->id)->get();

    expect($applicationPlayers)->toHaveCount(2);
    expect($applicationPlayers->pluck('player_id'))->toContain($pairPlayer->id);
});

test('doubles registration with a pair that has never played creates a new player record', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    $tournament = registrationTournament();
    $tournamentCategory = registrationCategory($tournament, ['name' => "Men's Doubles", 'gender' => 'male', 'team_size' => 2]);

    $this->actingAs($user);

    $component = Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->call('startRegistration');

    fillProfileStep($component, $tournamentCategory->id, ['regGender' => 'male'])
        ->set('pairHasAccount', 'no')
        ->set('pairPlayedBefore', 'no')
        ->call('registrationNextStep')
        ->assertHasNoErrors();

    expect($component->pairViaSearch)->toBeFalse();

    $component->set('pairFirstName', 'Mark')
        ->set('pairLastName', 'Santos')
        ->set('pairBirthDate', now()->subYears(22)->format('Y-m-d'))
        ->set('pairGender', 'male')
        ->set('pairAddress', 'Purok 3, Ozamiz City')
        ->set('pairSkillLevel', 'Beginner')
        ->call('registrationNextStep')
        ->assertHasNoErrors();

    $component->set('ownDocumentType', 'national_id')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->set('pairDocumentType', 'passport')
        ->set('pairDocument', UploadedFile::fake()->create('pair-id.jpg', 100, 'image/jpeg'))
        ->call('submitRegistration')
        ->assertHasNoErrors();

    $newPlayer = Player::where('name', 'Mark Santos')->sole();
    expect($newPlayer->user_id)->toBeNull();

    $application = PlayerApplication::where('tournament_category_id', $tournamentCategory->id)->sole();
    expect(ApplicationPlayer::where('application_id', $application->id)->where('player_id', $newPlayer->id)->exists())->toBeTrue();
});

test('a player cannot re-apply to the same category while an application is still active', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    $tournament = registrationTournament();
    $tournamentCategory = registrationCategory($tournament);

    $existingApplication = PlayerApplication::create([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => 'payment_pending',
    ]);
    ApplicationPlayer::create([
        'application_id' => $existingApplication->id,
        'player_id' => $player->id,
        'level_at_apply' => 'Beginner',
    ]);

    $this->actingAs($user);

    $component = Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->call('startRegistration');

    fillProfileStep($component, $tournamentCategory->id)
        ->call('registrationNextStep')
        ->assertHasNoErrors();

    $component->set('ownDocumentType', 'national_id')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('submitRegistration')
        ->assertHasErrors(['registration']);

    expect(PlayerApplication::where('tournament_category_id', $tournamentCategory->id)->count())->toBe(1);
});

test('a player can re-apply to the same category after a rejected application', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    $tournament = registrationTournament();
    $tournamentCategory = registrationCategory($tournament);

    $rejectedApplication = PlayerApplication::create([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => 'rejected',
    ]);
    ApplicationPlayer::create([
        'application_id' => $rejectedApplication->id,
        'player_id' => $player->id,
        'level_at_apply' => 'Beginner',
    ]);

    $this->actingAs($user);

    $component = Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->call('startRegistration');

    fillProfileStep($component, $tournamentCategory->id)
        ->call('registrationNextStep')
        ->assertHasNoErrors();

    $component->set('ownDocumentType', 'national_id')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('submitRegistration')
        ->assertHasNoErrors();

    expect(PlayerApplication::where('tournament_category_id', $tournamentCategory->id)->count())->toBe(2);
});
