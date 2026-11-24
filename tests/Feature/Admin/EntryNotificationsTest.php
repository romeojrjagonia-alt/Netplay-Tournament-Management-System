<?php

use App\Mail\ApplicationApproved;
use App\Mail\ApplicationRejected;
use App\Mail\MatchScheduled;
use App\Mail\PaymentRejected;
use App\Mail\PaymentVerified;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

/**
 * @param  list<array<string, mixed>>  $players
 */
function notifyEntry(string $status, array $players, int $teamSize = 1): PlayerApplication
{
    $tournament = Tournament::create([
        'name' => 'Notify Cup',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(5)->toDateString(),
        'date_end' => now()->addDays(6)->toDateString(),
        'registration_start' => now()->subDays(5)->toDateString(),
        'registration_end' => now()->addDays(3)->toDateString(),
        'status' => 'published',
    ]);

    $category = Category::create(['name' => 'Notify Category', 'gender' => 'mixed', 'team_size' => $teamSize]);
    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 150,
        'max_slots' => 8,
        'status' => 'open',
    ]);

    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => $status]);

    foreach ($players as $attributes) {
        $player = Player::create($attributes);
        ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);
    }

    return $application;
}

function notifyPayment(PlayerApplication $application, string $status = 'pending'): PaymentDetail
{
    return PaymentDetail::create([
        'application_id' => $application->id,
        'date_of_payment_made' => now(),
        'reference_number' => 'GC-1',
        'payment_status' => $status,
        'proof_of_payment' => 'proofs/p.png',
    ]);
}

beforeEach(function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('approving an application emails the player a payment link', function () {
    $application = notifyEntry('pending_verification', [['name' => 'Ann', 'email' => 'ann@example.com']]);

    Volt::test('admin.dashboard')->call('approveApplication', $application->id);

    Mail::assertQueued(ApplicationApproved::class, 1);
    Mail::assertQueued(ApplicationApproved::class, fn ($mail) => $mail->hasTo('ann@example.com')
        && str_contains($mail->render(), route('applications.payment', $application->reference_code)));
});

test('rejecting an application emails the reason', function () {
    $application = notifyEntry('pending_verification', [['name' => 'Ann', 'email' => 'ann@example.com']]);

    Volt::test('admin.dashboard')
        ->set('rejectionReason', 'Wrong category')
        ->call('rejectApplication', $application->id);

    Mail::assertQueued(ApplicationRejected::class, fn ($mail) => $mail->hasTo('ann@example.com') && str_contains($mail->render(), 'Wrong category'));
});

test('verifying a payment emails the player', function () {
    $application = notifyEntry('payment_pending', [['name' => 'Ann', 'email' => 'ann@example.com']]);
    $payment = notifyPayment($application);

    Volt::test('admin.dashboard')->set('activeTab', 'payments')->call('approvePayment', $payment->id);

    Mail::assertQueued(PaymentVerified::class, fn ($mail) => $mail->hasTo('ann@example.com'));
});

test('rejecting a payment emails a resubmit link', function () {
    $application = notifyEntry('payment_pending', [['name' => 'Ann', 'email' => 'ann@example.com']]);
    $payment = notifyPayment($application);

    Volt::test('admin.dashboard')->set('activeTab', 'payments')->call('rejectPayment', $payment->id);

    Mail::assertQueued(PaymentRejected::class, fn ($mail) => $mail->hasTo('ann@example.com')
        && str_contains($mail->render(), route('applications.payment', $application->reference_code)));
});

test('both players of a doubles entry are emailed, using the account email when the player has none', function () {
    $user = User::factory()->create(['role' => 'player', 'email' => 'account@example.com']);
    $application = notifyEntry('pending_verification', [
        ['name' => 'Ann', 'email' => 'ann@example.com'],
        ['name' => 'Ben', 'user_id' => $user->id],
    ], teamSize: 2);

    Volt::test('admin.dashboard')->call('approveApplication', $application->id);

    Mail::assertQueued(ApplicationApproved::class, 2);
    Mail::assertQueued(ApplicationApproved::class, fn ($mail) => $mail->hasTo('account@example.com'));
});

test('a player with no email is skipped and the decision still goes through', function () {
    $application = notifyEntry('pending_verification', [['name' => 'Phone Only', 'phone_number' => '09170000000']]);

    Volt::test('admin.dashboard')->call('approveApplication', $application->id);

    Mail::assertNothingQueued();
    expect($application->fresh()->status)->toBe('payment_pending');
});

test('match schedule emails are queued', function () {
    expect(is_subclass_of(MatchScheduled::class, ShouldQueue::class))->toBeTrue();
});
