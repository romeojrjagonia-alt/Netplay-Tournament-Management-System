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
use Livewire\Volt\Volt;

beforeEach(fn () => Storage::fake('public'));

function consentTestCategory(): TournamentCategory
{
    $tournament = Tournament::create([
        'name' => 'Consent Open',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(20),
        'date_end' => now()->addDays(21),
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addDays(10),
        'status' => 'published',
    ]);

    $category = Category::create(['name' => 'Singles', 'gender' => 'male', 'team_size' => 1]);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);
}

function fillConsentGuest($component, TournamentCategory $category, bool $withId = true)
{
    $component
        ->set('regFirstName', 'Juan')
        ->set('regLastName', 'Cruz')
        ->set('regBirthDate', '1998-03-01')
        ->set('regGender', 'male')
        ->set('regAddress', 'Purok 5, Ozamiz City')
        ->set('regSkillLevel', 'Level C')
        ->set('regPhoneNumber', '09171234567')
        ->set('regTournamentCategoryId', $category->id);

    if ($withId) {
        $component
            ->set('ownDocumentType', 'national_id')
            ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'));
    }

    return $component;
}

function fillConsentSignup($component, string $type = 'player')
{
    return $component
        ->call('selectUserType', $type)
        ->call('nextStep')
        ->set('firstName', 'Pedro')
        ->set('lastName', 'Reyes')
        ->set('email', 'pedro@example.com')
        ->set('password', 'Password123')
        ->set('password_confirmation', 'Password123')
        ->call('nextStep')
        ->set('birthDate', '1990-02-04')
        ->set('phoneNumber', '09171234567')
        ->set('gender', 'male')
        ->set('purok', 'Purok 5')
        ->set('city', 'Ozamiz City')
        ->set('province', 'Misamis Occidental');
}

// --- Consent at sign-up ----------------------------------------------------

test('sign-up opens on the consent dialog, before any personal detail is entered', function () {
    Volt::test('auth.register')
        ->assertSet('showConsent', true)
        ->assertSee('I Consent & Continue')
        ->assertSee('Decline & Exit');
});

test('sign-up saves nothing unless the visitor has consented', function () {
    fillConsentSignup(Volt::test('auth.register'))
        ->set('idDocumentType', 'national_id')
        ->set('idDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('register')
        ->assertSet('showConsent', true);

    expect(User::where('email', 'pedro@example.com')->exists())->toBeFalse();
});

test('consenting at sign-up closes the dialog, and the account records when consent was given', function () {
    Volt::test('auth.register')
        ->call('acceptConsent')
        ->assertSet('showConsent', false);

    fillConsentSignup(Volt::test('auth.register')->call('acceptConsent'))
        ->set('idDocumentType', 'national_id')
        ->set('idDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('register')
        ->assertHasNoErrors();

    $player = User::where('email', 'pedro@example.com')->first()->player;

    expect($player->data_consent_at)->not->toBeNull();
});

test('declining at sign-up creates nothing and returns to the landing page', function () {
    Volt::test('auth.register')
        ->call('declineConsent')
        ->assertRedirect(route('home'));

    expect(User::where('email', 'pedro@example.com')->exists())->toBeFalse();
});

// --- Valid ID at sign-up ---------------------------------------------------

test('every sign-up must include a valid ID', function (string $type) {
    fillConsentSignup(Volt::test('auth.register')->call('acceptConsent'), $type)
        ->call('register')
        ->assertHasErrors(['idDocumentType', 'idDocument']);
})->with(['player', 'umpire']);

test('the ID given at sign-up is stored on the player record', function () {
    fillConsentSignup(Volt::test('auth.register')->call('acceptConsent'))
        ->set('idDocumentType', 'others')
        ->set('idDocumentOther', 'Postal ID')
        ->set('idDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('register')
        ->assertHasNoErrors();

    $player = User::where('email', 'pedro@example.com')->first()->player;

    expect($player->hasIdOnFile())->toBeTrue()
        ->and($player->id_document_type)->toBe('others')
        ->and($player->id_document_other)->toBe('Postal ID');

    Storage::disk('public')->assertExists($player->id_document_path);
});

// --- Consent at tournament registration -----------------------------------

test('the tournament form opens on the consent dialog, before any personal detail', function () {
    $category = consentTestCategory();

    Volt::test('tournaments.register', ['tournament' => $category->tournament])
        ->assertSet('showConsent', true)
        ->assertSee('I Consent & Continue')
        ->assertSee('Decline & Exit');
});

test('a tournament registration stores nothing unless the registrant has consented', function () {
    $category = consentTestCategory();

    fillConsentGuest(Volt::test('tournaments.register', ['tournament' => $category->tournament]), $category)
        ->call('submit')
        ->assertSet('showConsent', true);

    expect(PlayerApplication::count())->toBe(0)
        ->and(Player::count())->toBe(0);
});

test('consenting reveals the form, and the submitted application records when consent was given', function () {
    $category = consentTestCategory();

    $component = Volt::test('tournaments.register', ['tournament' => $category->tournament])
        ->call('acceptConsent')
        ->assertSet('showConsent', false)
        ->assertSee('First Name');

    fillConsentGuest($component, $category)
        ->call('submit')
        ->assertHasNoErrors();

    expect(PlayerApplication::first()->data_consent_at)->not->toBeNull();
});

test('declining a tournament registration stores nothing', function () {
    $category = consentTestCategory();

    fillConsentGuest(Volt::test('tournaments.register', ['tournament' => $category->tournament]), $category)
        ->call('declineConsent');

    expect(PlayerApplication::count())->toBe(0)
        ->and(Player::count())->toBe(0);
});

test('the dashboard registration asks for consent first too', function () {
    $category = consentTestCategory();
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    $component = Volt::actingAs($user)->test('player.events', ['tournament' => $category->tournament->id])
        ->set('viewingTournamentId', $category->tournament_id)
        ->call('startRegistration')
        ->assertSet('showConsent', true)
        ->set('regFirstName', 'Juan')
        ->set('regLastName', 'Cruz')
        ->set('regBirthDate', '1998-03-01')
        ->set('regGender', 'male')
        ->set('regAddress', 'Purok 5, Ozamiz City')
        ->set('regSkillLevel', 'Beginner')
        ->set('regPhoneNumber', '09171234567')
        ->set('regTournamentCategoryId', $category->id)
        ->set('ownDocumentType', 'national_id')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('submitRegistration')
        ->assertSet('showConsent', true);

    expect(PlayerApplication::count())->toBe(0);

    $component->call('declineRegistrationConsent');

    expect(PlayerApplication::count())->toBe(0);

    $component->call('acceptRegistrationConsent')->assertSet('showConsent', false)->call('submitRegistration');

    expect(PlayerApplication::first()->data_consent_at)->not->toBeNull();
});

// --- Valid ID is submitted once -------------------------------------------

test('a player with an ID on file is not asked for one again and the stored ID is reused', function () {
    $category = consentTestCategory();
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'gender' => 'male',
        'id_document_type' => 'passport',
        'id_document_path' => 'id-documents/original.jpg',
    ]);

    $component = Volt::actingAs($user)->test('tournaments.register', ['tournament' => $category->tournament]);

    fillConsentGuest($component->call('acceptConsent'), $category, withId: false)
        ->call('submit')
        ->assertHasNoErrors();

    $applicationPlayer = ApplicationPlayer::first();

    expect($applicationPlayer->id_document_type)->toBe('passport')
        ->and($applicationPlayer->id_document_path)->toBe('id-documents/original.jpg')
        ->and($player->fresh()->id_document_path)->toBe('id-documents/original.jpg');
});

test('a first tournament entry saves the uploaded ID on the player for next time', function () {
    $category = consentTestCategory();
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    fillConsentGuest(Volt::actingAs($user)->test('tournaments.register', ['tournament' => $category->tournament])->call('acceptConsent'), $category)
        ->call('submit')
        ->assertHasNoErrors();

    expect($player->fresh()->hasIdOnFile())->toBeTrue();
});

test('a player without an ID on file must upload one to register', function () {
    $category = consentTestCategory();
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    fillConsentGuest(Volt::actingAs($user)->test('tournaments.register', ['tournament' => $category->tournament]), $category, withId: false)
        ->call('submit')
        ->assertHasErrors(['ownDocumentType', 'ownDocument']);
});

test('accounts without an ID on file are asked for one once and then never again', function () {
    $user = User::factory()->create(['role' => 'umpire']);
    Player::create(['user_id' => $user->id, 'name' => $user->name]);

    Volt::actingAs($user)->test('account.id-prompt')
        ->assertSet('show', true)
        ->call('save')
        ->assertHasErrors(['documentType', 'document'])
        ->set('documentType', 'drivers_license')
        ->set('document', UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('show', false);

    expect($user->fresh()->player->hasIdOnFile())->toBeTrue();

    Volt::actingAs($user->fresh())->test('account.id-prompt')->assertSet('show', false);
});

test('admins are never asked for an ID', function () {
    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('account.id-prompt')
        ->assertSet('show', false);
});

// --- Skill level dropdown --------------------------------------------------

test('the skill level is a dropdown limited to the allowed levels', function () {
    expect(Category::PLAYER_LEVELS)->toBe([
        'Beginner', 'Intermediate', 'Level G', 'Level F', 'Level E', 'Level D', 'Level C', 'Level B', 'Level A',
    ]);

    $category = consentTestCategory();

    $component = Volt::test('tournaments.register', ['tournament' => $category->tournament])->call('acceptConsent');

    fillConsentGuest($component, $category)
        ->set('regSkillLevel', 'Grandmaster')
        ->call('submit')
        ->assertHasErrors(['regSkillLevel']);

    foreach (Category::PLAYER_LEVELS as $level) {
        $component->assertSeeHtml('<option value="'.$level.'"');
    }
});

test('a skill level typed by hand is rejected for the partner as well', function () {
    $category = consentTestCategory();
    $doubles = Category::create(['name' => 'Doubles', 'gender' => 'male', 'team_size' => 2]);
    $doublesCategory = TournamentCategory::create([
        'tournament_id' => $category->tournament_id,
        'category_id' => $doubles->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);

    fillConsentGuest(Volt::test('tournaments.register', ['tournament' => $category->tournament]), $doublesCategory)
        ->call('enterPairManually')
        ->set('pairFirstName', 'Pedro')
        ->set('pairLastName', 'Santos')
        ->set('pairBirthDate', '1997-01-01')
        ->set('pairGender', 'male')
        ->set('pairAddress', 'Ozamiz City')
        ->set('pairSkillLevel', 'Expert')
        ->set('regStep', 2)
        ->call('nextStep')
        ->assertHasErrors(['pairSkillLevel']);
});

// --- Consent dialog content --------------------------------------------------

test('the consent dialog explains what is collected, why the ID is needed, retention and rights', function () {
    Volt::test('auth.register')
        ->assertSee('Profile & contact info')
        ->assertSee('Identity verification')
        ->assertSee('Tournament records')
        ->assertSee('How long we keep it')
        ->assertSee('Your rights')
        ->assertSee('Read our full Privacy Policy')
        ->assertSee('I have read and agree to the collection and use of my data')
        ->assertSee('parent or legal guardian')
        ->assertSeeHtml('href="'.route('privacy').'"');
});

test('the privacy policy page is public', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('How long we keep it')
        ->assertSee('Your rights');
});

test('the privacy policy page has a back to NetPlay button', function () {
    $this->get(route('privacy'))
        ->assertSee('Back to NetPlay')
        ->assertSeeHtml('href="'.route('home').'"');
});
