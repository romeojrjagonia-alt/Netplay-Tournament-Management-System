<?php

use App\Mail\ApplicationCancelled;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

function cancellationTournament(): Tournament
{
    return Tournament::create([
        'name' => 'Cancel Cup',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(20)->toDateString(),
        'date_end' => now()->addDays(21)->toDateString(),
        'time' => '9:00 AM - 5:00 PM',
        'specific_days' => 'SAT, SUN',
        'registration_start' => '2026-06-01',
        'registration_end' => now()->addDays(10)->toDateString(),
        'status' => 'published',
    ]);
}

function cancellationCategory(Tournament $tournament, string $name = "Men's Singles", int $teamSize = 1): TournamentCategory
{
    $category = Category::create(['name' => $name, 'gender' => 'male', 'team_size' => $teamSize]);

    return TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);
}

function cancellationApplication(TournamentCategory $tournamentCategory, string $status, Player ...$players): PlayerApplication
{
    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => $status]);

    foreach ($players as $player) {
        ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);
    }

    return $application;
}

function cancellationPlayerUser(): array
{
    $user = User::factory()->create(['role' => 'player']);
    $player = Player::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => 'male']);

    return [$user, $player];
}

test('a player with a pending application sees a message instead of the register button', function () {
    [$user, $player] = cancellationPlayerUser();
    $tournament = cancellationTournament();
    cancellationApplication(cancellationCategory($tournament), 'pending_verification', $player);
    cancellationCategory($tournament, "Men's Doubles", 2);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewTournament', $tournament->id)
        ->assertSee('You already have a pending application for this tournament.')
        ->assertDontSee('wire:click="startRegistration"', false)
        ->call('startRegistration')
        ->assertDispatched('system-message');
});

test('a payment pending application also blocks a new one in the same tournament', function () {
    [, $player] = cancellationPlayerUser();
    $tournament = cancellationTournament();
    cancellationApplication(cancellationCategory($tournament), 'payment_pending', $player);

    expect($player->hasPendingApplicationIn($tournament->id))->toBeTrue();
});

test('approved or rejected applications and other tournaments do not block', function () {
    [, $player] = cancellationPlayerUser();
    $tournament = cancellationTournament();
    cancellationApplication(cancellationCategory($tournament), 'approved', $player);
    cancellationApplication(cancellationCategory($tournament, 'Other'), 'rejected', $player);

    expect($player->hasPendingApplicationIn($tournament->id))->toBeFalse()
        ->and($player->hasPendingApplicationIn(cancellationTournament()->id))->toBeFalse();
});

test('submitting is refused for the applicant and for a partner with a pending application', function () {
    [$user, $player] = cancellationPlayerUser();
    $tournament = cancellationTournament();
    $singles = cancellationCategory($tournament);
    $doubles = cancellationCategory($tournament, "Men's Doubles", 2);
    cancellationApplication($singles, 'pending_verification', $player);

    $this->actingAs($user);

    Volt::test('player.events')
        ->set('viewingTournamentId', $tournament->id)
        ->set('regTournamentCategoryId', $doubles->id)
        ->set('regFirstName', 'Juan')
        ->set('regLastName', 'Cruz')
        ->set('regBirthDate', now()->subYears(25)->format('Y-m-d'))
        ->set('regGender', 'male')
        ->set('regAddress', 'Ozamiz')
        ->set('regSkillLevel', 'Beginner')
        ->set('pairHasAccount', 'yes')
        ->set('pairPlayerId', $player->id)
        ->set('ownDocumentType', 'passport')
        ->set('ownDocument', UploadedFile::fake()->create('id.jpg', 10, 'image/jpeg'))
        ->set('pairDocumentType', 'passport')
        ->set('pairDocument', UploadedFile::fake()->create('id2.jpg', 10, 'image/jpeg'))
        ->call('submitRegistration')
        ->assertDispatched('system-message');

    expect(PlayerApplication::count())->toBe(1);
});

test('a player can cancel a pending application with no payment and the partner is emailed', function () {
    Mail::fake();

    [$user, $player] = cancellationPlayerUser();
    $partner = Player::create(['name' => 'Pat Partner', 'email' => 'pat@example.com']);
    $application = cancellationApplication(cancellationCategory(cancellationTournament(), 'Doubles', 2), 'payment_pending', $player, $partner);
    PaymentDetail::create([
        'application_id' => $application->id,
        'date_of_payment_made' => now(),
        'reference_number' => '1',
        'payment_status' => 'rejected',
        'proof_of_payment' => 'payment-proofs/old.png',
    ]);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewApplication', $application->id)
        ->assertSee('Cancel application')
        ->call('cancelViewingApplication')
        ->assertDispatched('system-message');

    expect(PlayerApplication::find($application->id))->toBeNull()
        ->and(ApplicationPlayer::count())->toBe(0);

    Mail::assertQueued(ApplicationCancelled::class, fn ($mail) => $mail->hasTo('pat@example.com'));
    Mail::assertQueued(ApplicationCancelled::class, 1);
});

test('an application with a pending or verified payment cannot be cancelled', function (string $paymentStatus) {
    [$user, $player] = cancellationPlayerUser();
    $application = cancellationApplication(cancellationCategory(cancellationTournament()), 'payment_pending', $player);
    PaymentDetail::create([
        'application_id' => $application->id,
        'date_of_payment_made' => now(),
        'reference_number' => '1',
        'payment_status' => $paymentStatus,
        'proof_of_payment' => 'payment-proofs/x.png',
    ]);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewApplication', $application->id)
        ->call('cancelViewingApplication')
        ->assertForbidden();

    expect(PlayerApplication::find($application->id))->not->toBeNull();
})->with(['pending', 'verified']);

test('an approved application cannot be cancelled', function () {
    [$user, $player] = cancellationPlayerUser();
    $application = cancellationApplication(cancellationCategory(cancellationTournament()), 'approved', $player);

    $this->actingAs($user);

    Volt::test('player.events')
        ->call('viewApplication', $application->id)
        ->call('cancelViewingApplication')
        ->assertForbidden();
});

test('a guest can cancel by reference code', function () {
    Mail::fake();

    $player = Player::create(['name' => 'Walk In']);
    $application = cancellationApplication(cancellationCategory(cancellationTournament()), 'pending_verification', $player);

    Volt::test('applications.lookup')
        ->set('code', $application->reference_code)
        ->call('search')
        ->assertSee('Cancel application')
        ->call('cancelApplication');

    expect(PlayerApplication::find($application->id))->toBeNull();
});

test('an admin can cancel a pending application from the event page', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $player = Player::create(['name' => 'Walk In']);
    $tournament = cancellationTournament();
    $application = cancellationApplication(cancellationCategory($tournament), 'pending_verification', $player);

    $this->actingAs($admin);

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('cancelApplication', $application->id);

    expect(PlayerApplication::find($application->id))->toBeNull();
});
