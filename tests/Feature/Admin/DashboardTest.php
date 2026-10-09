<?php

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function dashboardTournament(array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
        'name' => 'Inato Tournament',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-07-01',
        'date_end' => '2026-07-31',
        'registration_start' => '2026-06-01',
        'registration_end' => '2026-12-31',
        'status' => 'published',
    ], $overrides));
}

function dashboardTournamentCategory(Tournament $tournament, array $categoryOverrides = [], array $tournamentCategoryOverrides = []): TournamentCategory
{
    $category = Category::create(array_merge([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
    ], $categoryOverrides));

    return TournamentCategory::create(array_merge([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ], $tournamentCategoryOverrides));
}

function dashboardPlayer(array $overrides = []): Player
{
    return Player::create(array_merge([
        'name' => 'Juan Torres',
        'birth_date' => '1999-01-12',
        'gender' => 'male',
        'phone_number' => '09237138493',
        'address' => 'Labo, Ozamiz City',
        'level' => 'Level G',
    ], $overrides));
}

/**
 * @param  array<int, Player>  $players
 */
function dashboardApplication(TournamentCategory $tournamentCategory, array $players, string $status): PlayerApplication
{
    $application = PlayerApplication::create([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => $status,
    ]);

    foreach ($players as $player) {
        ApplicationPlayer::create([
            'application_id' => $application->id,
            'player_id' => $player->id,
            'level_at_apply' => $player->level,
        ]);
    }

    return $application;
}

function dashboardAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('a non-admin cannot view the dashboard', function () {
    $user = User::factory()->create(['role' => 'player']);

    $this->actingAs($user);

    Volt::test('admin.dashboard')->assertForbidden();
});

test('pending approvals tab only lists applications awaiting verification', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament);

    dashboardApplication($tournamentCategory, [dashboardPlayer(['name' => 'Marcus Villanueva'])], 'pending_verification');
    dashboardApplication($tournamentCategory, [dashboardPlayer(['name' => 'Approved Player'])], 'approved');
    dashboardApplication($tournamentCategory, [dashboardPlayer(['name' => 'Rejected Player'])], 'rejected');

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->assertSee('Marcus Villanueva')
        ->assertDontSee('Approved Player')
        ->assertDontSee('Rejected Player')
        ->assertSet('totals.pendingApprovals', 1);
});

test('pending payments tab only lists payments awaiting verification', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament);

    $pendingApplication = dashboardApplication($tournamentCategory, [dashboardPlayer(['name' => 'Ella Bautista'])], 'payment_pending');
    PaymentDetail::create([
        'application_id' => $pendingApplication->id,
        'date_of_payment_made' => now(),
        'reference_number' => '0987654321',
        'payment_status' => 'pending',
        'proof_of_payment' => 'payment-proofs/demo.png',
    ]);

    $verifiedApplication = dashboardApplication($tournamentCategory, [dashboardPlayer(['name' => 'Verified Player'])], 'approved');
    PaymentDetail::create([
        'application_id' => $verifiedApplication->id,
        'date_of_payment_made' => now(),
        'reference_number' => '1111111111',
        'payment_status' => 'verified',
        'proof_of_payment' => 'payment-proofs/demo.png',
    ]);

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->set('activeTab', 'payments')
        ->assertSee('Ella Bautista')
        ->assertDontSee('Verified Player')
        ->assertSet('totals.pendingPayments', 1);
});

test('viewing a pending application shows both players\' details', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament, ['name' => 'Mixed Doubles', 'gender' => 'mixed', 'team_size' => 2, 'level' => 'Level G']);

    $player = dashboardPlayer(['name' => 'Juan Torres']);
    $partner = dashboardPlayer(['name' => 'Maria Cruz', 'gender' => 'female']);
    $application = dashboardApplication($tournamentCategory, [$player, $partner], 'pending_verification');

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->call('viewApplication', $application->id)
        ->assertSee('Juan Torres')
        ->assertSee('Maria Cruz')
        ->assertSee('Level G')
        ->assertSee('Labo, Ozamiz City');
});

test('viewing a pending payment shows the payment and player details', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament);
    $application = dashboardApplication($tournamentCategory, [dashboardPlayer(['name' => 'Ella Bautista'])], 'payment_pending');
    $payment = PaymentDetail::create([
        'application_id' => $application->id,
        'date_of_payment_made' => '2026-06-29',
        'reference_number' => '0936592561235',
        'payment_status' => 'pending',
        'proof_of_payment' => 'payment-proofs/demo.png',
    ]);

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->set('activeTab', 'payments')
        ->call('viewPayment', $payment->id)
        ->assertSee('Ella Bautista')
        ->assertSee('June 29, 2026')
        ->assertSee('0936592561235')
        ->assertSee('View Attachment');
});

test('approving an application moves it to payment pending', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament);
    $application = dashboardApplication($tournamentCategory, [dashboardPlayer()], 'pending_verification');

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->call('approveApplication', $application->id)
        ->assertSet('totals.pendingApprovals', 0)
        ->assertDispatched('modal-close', name: 'application-details')
        ->assertDispatched('admin-toast', variant: 'success');

    expect($application->fresh()->status)->toBe('payment_pending');
});

test('rejecting an application marks it rejected', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament);
    $application = dashboardApplication($tournamentCategory, [dashboardPlayer()], 'pending_verification');

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->call('rejectApplication', $application->id)
        ->assertDispatched('modal-close', name: 'application-details')
        ->assertDispatched('admin-toast', variant: 'danger');

    expect($application->fresh()->status)->toBe('rejected');
});

test('rejecting an application stores the optional reason for the applicant', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament);
    $application = dashboardApplication($tournamentCategory, [dashboardPlayer()], 'pending_verification');

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->set('rejectionReason', 'Age is outside the category range')
        ->call('rejectApplication', $application->id);

    expect($application->fresh())
        ->status->toBe('rejected')
        ->rejection_reason->toBe('Age is outside the category range');
});

test('approving a payment verifies it and approves the application', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament);
    $application = dashboardApplication($tournamentCategory, [dashboardPlayer()], 'payment_pending');
    $payment = PaymentDetail::create([
        'application_id' => $application->id,
        'date_of_payment_made' => now(),
        'reference_number' => '0987654321',
        'payment_status' => 'pending',
        'proof_of_payment' => 'payment-proofs/demo.png',
    ]);

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->set('activeTab', 'payments')
        ->call('approvePayment', $payment->id)
        ->assertSet('totals.pendingPayments', 0)
        ->assertDispatched('modal-close', name: 'payment-details')
        ->assertDispatched('admin-toast', variant: 'success');

    expect($payment->fresh()->payment_status)->toBe('verified');
    expect($application->fresh()->status)->toBe('approved');
});

test('rejecting a payment keeps the application in payment pending', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament);
    $application = dashboardApplication($tournamentCategory, [dashboardPlayer()], 'payment_pending');
    $payment = PaymentDetail::create([
        'application_id' => $application->id,
        'date_of_payment_made' => now(),
        'reference_number' => '0987654321',
        'payment_status' => 'pending',
        'proof_of_payment' => 'payment-proofs/demo.png',
    ]);

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->set('activeTab', 'payments')
        ->call('rejectPayment', $payment->id)
        ->assertDispatched('modal-close', name: 'payment-details')
        ->assertDispatched('admin-toast', variant: 'danger');

    expect($payment->fresh()->payment_status)->toBe('rejected');
    expect($application->fresh()->status)->toBe('payment_pending');
});

test('search filters rows by player name', function () {
    $tournament = dashboardTournament();
    $tournamentCategory = dashboardTournamentCategory($tournament);
    dashboardApplication($tournamentCategory, [dashboardPlayer(['name' => 'Marcus Villanueva'])], 'pending_verification');
    dashboardApplication($tournamentCategory, [dashboardPlayer(['name' => 'Ramon Dela Cruz'])], 'pending_verification');

    $this->actingAs(dashboardAdmin());

    Volt::test('admin.dashboard')
        ->set('search', 'Marcus')
        ->assertSee('Marcus Villanueva')
        ->assertDontSee('Ramon Dela Cruz');
});
