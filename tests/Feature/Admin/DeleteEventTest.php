<?php

use App\Mail\EventCancelled;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Mail::fake();
    Storage::fake('public');

    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

function deleteEventTournament(array $overrides = []): Tournament
{
    $tournament = Tournament::create(array_merge([
        'name' => 'Delete Cup',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(10)->toDateString(),
        'date_end' => now()->addDays(11)->toDateString(),
        'registration_start' => now()->subDays(5)->toDateString(),
        'registration_end' => now()->addDays(5)->toDateString(),
        'status' => 'published',
    ], $overrides));

    $category = Category::create(['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1]);

    TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 200,
        'max_slots' => 8,
        'status' => 'open',
    ]);

    return $tournament;
}

function deleteEventEntry(Tournament $tournament, string $status, string $name, ?string $paymentStatus = null): PlayerApplication
{
    $application = PlayerApplication::create([
        'tournament_category_id' => $tournament->tournamentCategories()->value('id'),
        'status' => $status,
    ]);

    $player = Player::create(['name' => $name, 'level' => 'Beginner', 'email' => str($name)->slug().'@example.com']);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);

    if ($paymentStatus) {
        $path = "payment-proofs/{$application->id}.png";
        Storage::disk('public')->put($path, 'proof');

        PaymentDetail::create([
            'application_id' => $application->id,
            'date_of_payment_made' => now(),
            'reference_number' => '1234567890',
            'payment_status' => $paymentStatus,
            'proof_of_payment' => $path,
        ]);
    }

    return $application;
}

/**
 * @param  list<PlayerApplication>  $applications
 */
function deleteEventMatch(Tournament $tournament, array $applications, string $status = 'completed'): Matches
{
    $match = Matches::create([
        'tournament_category_id' => $tournament->tournamentCategories()->value('id'),
        'bracket_type' => 'single_elimination',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
    ]);

    foreach ($applications as $application) {
        MatchApplication::create(['match_id' => $match->id, 'application_id' => $application->id]);
    }

    $match->update(['status' => $status, 'winner_application_id' => $applications[0]->id]);

    return $match;
}

test('an event with no finalized records is hard-deleted along with its files', function () {
    Storage::disk('public')->put('posters/cup.png', 'poster');
    $tournament = deleteEventTournament(['poster_path' => 'posters/cup.png']);
    deleteEventEntry($tournament, 'pending_verification', 'Pending Player');
    $unpaid = deleteEventEntry($tournament, 'approved', 'Unpaid Player', 'pending');

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('deleteEvent')
        ->assertRedirect(route('admin.events.index'));

    expect(Tournament::find($tournament->id))->toBeNull()
        ->and(PlayerApplication::count())->toBe(0)
        ->and(Player::count())->toBe(2);

    Storage::disk('public')->assertMissing('posters/cup.png');
    Storage::disk('public')->assertMissing("payment-proofs/{$unpaid->id}.png");

    Mail::assertQueued(EventCancelled::class, 2);
    Mail::assertQueued(EventCancelled::class, fn (EventCancelled $mail) => $mail->hasTo('pending-player@example.com') && $mail->tournamentName === 'Delete Cup');
});

test('a draft event is hard-deleted', function () {
    $tournament = deleteEventTournament(['status' => 'draft']);

    Volt::test('admin.events.show', ['tournament' => $tournament])->call('deleteEvent');

    expect(Tournament::find($tournament->id))->toBeNull();
    Mail::assertNothingQueued();
});

test('an event with an approved and payment-verified entry cannot be deleted', function () {
    $tournament = deleteEventTournament();
    $finalized = deleteEventEntry($tournament, 'approved', 'Paid Player', 'verified');

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->assertDontSeeHtml('$wire.deleteEvent()')
        ->call('deleteEvent')
        ->assertNoRedirect()
        ->assertDispatched('system-message', title: 'Cannot delete event', variant: 'danger');

    expect($tournament->fresh())->not->toBeNull()
        ->and($tournament->fresh()->is_concluded)->toBeFalse()
        ->and(PlayerApplication::find($finalized->id))->not->toBeNull();

    Storage::disk('public')->assertExists("payment-proofs/{$finalized->id}.png");
    Mail::assertNothingQueued();
});

test('an event with played matches cannot be deleted, keeping player statistics intact', function () {
    $tournament = deleteEventTournament();
    $winner = deleteEventEntry($tournament, 'approved', 'Winner', 'pending');
    $loser = deleteEventEntry($tournament, 'approved', 'Loser', 'pending');
    deleteEventMatch($tournament, [$winner, $loser]);

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->call('deleteEvent')
        ->assertDispatched('system-message', title: 'Cannot delete event');

    expect(Tournament::find($tournament->id))->not->toBeNull()
        ->and(Matches::count())->toBe(1)
        ->and(PlayerStatistic::sum('matches_played'))->toBe(2)
        ->and(PlayerStatistic::sum('wins'))->toBe(1);
});

test('a completed bye is not a played match, so the event is hard-deleted', function () {
    $tournament = deleteEventTournament();
    $lone = deleteEventEntry($tournament, 'approved', 'Lone Player', 'pending');
    deleteEventMatch($tournament, [$lone]);

    Volt::test('admin.events.show', ['tournament' => $tournament])->call('deleteEvent');

    expect(Tournament::find($tournament->id))->toBeNull()
        ->and(Matches::count())->toBe(0);
});

test('a concluded event cannot be deleted', function () {
    $tournament = deleteEventTournament(['concluded_at' => now()]);

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->assertDontSee('Delete Event')
        ->call('deleteEvent')
        ->assertForbidden();

    expect(Tournament::find($tournament->id))->not->toBeNull();
});
