<?php

use App\Models\Category;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

function lookupApplication(string $status, array $attributes = []): PlayerApplication
{
    $tournament = Tournament::create([
        'name' => 'Lookup Open',
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

    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);

    $application = PlayerApplication::create(array_merge([
        'tournament_category_id' => $tournamentCategory->id,
        'status' => $status,
    ], $attributes));

    $application->applicationPlayers()->create([
        'player_id' => Player::create(['name' => 'Juan Cruz'])->id,
        'level_at_apply' => 'Beginner',
    ]);

    return $application;
}

test('every application gets a unique reference code', function () {
    $first = lookupApplication('pending_verification');
    $second = lookupApplication('pending_verification');

    expect($first->reference_code)->toMatch('/^NP-[A-Z0-9]{6}$/')
        ->and($second->reference_code)->not->toBe($first->reference_code);
});

test('the landing page shows the reference code lookup', function () {
    $this->get('/')->assertOk()->assertSee('Application Reference Code');
});

test('an unknown code shows an error and no application', function () {
    Volt::test('applications.lookup')
        ->set('code', 'NP-NOPE00')
        ->call('search')
        ->assertHasErrors('code')
        ->assertSet('foundCode', null);
});

test('a pending application shows it is waiting for verification without a payment button', function () {
    $application = lookupApplication('pending_verification');

    Volt::test('applications.lookup')
        ->set('code', strtolower($application->reference_code))
        ->call('search')
        ->assertSet('foundCode', $application->reference_code)
        ->assertDispatched('modal-show', name: 'application-lookup')
        ->assertSee('Waiting for verification')
        ->assertSee('Lookup Open')
        ->assertSee('Juan Cruz')
        ->assertDontSee('Proceed to Payment');
});

test('an approved application offers the payment form', function () {
    $application = lookupApplication('payment_pending');

    Volt::test('applications.lookup')
        ->set('code', $application->reference_code)
        ->call('search')
        ->assertSee('Proceed to Payment')
        ->assertSee(route('applications.payment', $application->reference_code), false);
});

test('a rejected application shows the reason', function () {
    $application = lookupApplication('rejected', ['rejection_reason' => 'Age is outside the category range']);

    Volt::test('applications.lookup')
        ->set('code', $application->reference_code)
        ->call('search')
        ->assertSee('Rejected')
        ->assertSee('Age is outside the category range')
        ->assertDontSee('Proceed to Payment');
});

test('a rejected application without a reason says so', function () {
    $application = lookupApplication('rejected');

    Volt::test('applications.lookup')
        ->set('code', $application->reference_code)
        ->call('search')
        ->assertSee('No reason provided.');
});

test('the payment page is only open for approved applications', function () {
    $pending = lookupApplication('pending_verification');
    $approved = lookupApplication('payment_pending');

    $this->get(route('applications.payment', $pending->reference_code))
        ->assertOk()
        ->assertSee('been approved yet');

    $this->get(route('applications.payment', $approved->reference_code))
        ->assertOk()
        ->assertSee('GCash Reference Number');

    $this->get(route('applications.payment', 'NP-NOPE00'))->assertNotFound();
});

test('a guest submits proof of payment for an approved application', function () {
    Storage::fake('public');

    $application = lookupApplication('payment_pending');

    Volt::test('applications.payment', ['reference' => $application->reference_code])
        ->set('referenceNumber', '1234 567 890123')
        ->set('datePaid', now()->format('Y-m-d'))
        ->set('proof', UploadedFile::fake()->create('gcash.png', 100, 'image/png'))
        ->call('submitPayment')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $payment = PaymentDetail::firstOrFail();

    expect($payment->application_id)->toBe($application->id)
        ->and($payment->payment_status)->toBe('pending')
        ->and($payment->reference_number)->toBe('1234 567 890123');

    Storage::disk('public')->assertExists($payment->proof_of_payment);
});

test('payment proof is required', function () {
    $application = lookupApplication('payment_pending');

    Volt::test('applications.payment', ['reference' => $application->reference_code])
        ->set('referenceNumber', '')
        ->call('submitPayment')
        ->assertHasErrors(['referenceNumber', 'proof']);

    expect(PaymentDetail::count())->toBe(0);
});

test('payment cannot be submitted before approval or while a proof is under review', function () {
    Storage::fake('public');

    $pending = lookupApplication('pending_verification');

    Volt::test('applications.payment', ['reference' => $pending->reference_code])
        ->set('referenceNumber', '123')
        ->set('proof', UploadedFile::fake()->create('gcash.png', 100, 'image/png'))
        ->call('submitPayment')
        ->assertHasErrors('payment');

    $underReview = lookupApplication('payment_pending');
    $underReview->paymentDetails()->create([
        'date_of_payment_made' => now(),
        'payment_status' => 'pending',
        'proof_of_payment' => 'payment-proofs/x.png',
    ]);

    Volt::test('applications.payment', ['reference' => $underReview->reference_code])
        ->set('referenceNumber', '123')
        ->set('proof', UploadedFile::fake()->create('gcash.png', 100, 'image/png'))
        ->call('submitPayment')
        ->assertHasErrors('payment');

    expect(PaymentDetail::count())->toBe(1);
});

test('a rejected payment can be resubmitted', function () {
    Storage::fake('public');

    $application = lookupApplication('payment_pending');
    $application->paymentDetails()->create([
        'date_of_payment_made' => now(),
        'payment_status' => 'rejected',
        'proof_of_payment' => 'payment-proofs/old.png',
    ]);

    Volt::test('applications.lookup')
        ->set('code', $application->reference_code)
        ->call('search')
        ->assertSee('Payment rejected')
        ->assertSee('Resubmit Payment');

    Volt::test('applications.payment', ['reference' => $application->reference_code])
        ->set('referenceNumber', '999')
        ->set('proof', UploadedFile::fake()->create('new.png', 100, 'image/png'))
        ->call('submitPayment')
        ->assertHasNoErrors();

    expect(PaymentDetail::count())->toBe(2);
});
