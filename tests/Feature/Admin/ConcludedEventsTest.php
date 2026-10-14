<?php

use App\Actions\Applications\RerouteApplication;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Livewire\Volt\Volt;

function auditTournament(array $overrides = []): Tournament
{
    return Tournament::create(array_merge([
        'name' => 'Audit Cup',
        'format' => 'single_elimination',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => now()->addDays(5)->toDateString(),
        'date_end' => now()->addDays(6)->toDateString(),
        'registration_start' => now()->subDays(5)->toDateString(),
        'registration_end' => now()->addDays(3)->toDateString(),
        'status' => 'published',
    ], $overrides));
}

function auditCategory(Tournament $tournament, array $categoryOverrides = [], array $overrides = []): TournamentCategory
{
    $category = Category::create(array_merge([
        'name' => "Men's Singles",
        'gender' => 'male',
        'team_size' => 1,
    ], $categoryOverrides));

    return TournamentCategory::create(array_merge([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 200,
        'max_slots' => 8,
        'status' => 'open',
    ], $overrides));
}

function auditEntry(TournamentCategory $tournamentCategory, string $name, string $status = 'approved'): PlayerApplication
{
    $player = Player::create(['name' => $name, 'gender' => 'male', 'level' => 'Beginner']);
    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => $status]);
    ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);

    return $application;
}

function auditAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('an event is not concluded on its last day, but is the day after', function () {
    expect(auditTournament(['date_end' => today()->toDateString()])->is_concluded)->toBeFalse()
        ->and(auditTournament(['date_end' => today()->subDay()->toDateString()])->is_concluded)->toBeTrue();
});

test('the last day of an event is listed under Published, not Concluded', function () {
    auditTournament(['name' => 'Final Day Cup', 'date_start' => today()->subDay()->toDateString(), 'date_end' => today()->toDateString()]);

    $this->actingAs(auditAdmin());

    Volt::test('admin.events.index')
        ->call('selectTab', 'published')
        ->assertSee('Final Day Cup')
        ->call('selectTab', 'concluded')
        ->assertDontSee('Final Day Cup');
});

test('an admin can conclude an event, which moves it to the Concluded tab and closes registration', function () {
    $tournament = auditTournament(['name' => 'Manual Cup']);
    auditCategory($tournament);

    $this->actingAs(auditAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])->call('concludeEvent');

    expect($tournament->fresh()->concluded_at)->not->toBeNull()
        ->and($tournament->fresh()->is_concluded)->toBeTrue()
        ->and(Tournament::acceptingRegistrations()->whereKey($tournament->id)->exists())->toBeFalse();

    Volt::test('admin.events.index')
        ->call('selectTab', 'concluded')
        ->assertSee('Manual Cup')
        ->call('selectTab', 'published')
        ->assertDontSee('Manual Cup');
});

test('a manually concluded event can be reopened before its end date passes', function () {
    $tournament = auditTournament(['concluded_at' => now()]);

    $this->actingAs(auditAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])->call('reopenEvent');

    expect($tournament->fresh()->concluded_at)->toBeNull();
});

test('players cannot see or register for a concluded event', function () {
    $user = User::factory()->create(['role' => 'player']);
    Player::create(['user_id' => $user->id, 'name' => $user->name]);

    $concluded = auditTournament(['name' => 'Done Cup', 'concluded_at' => now()]);
    auditCategory($concluded);
    $pastEnd = auditTournament(['name' => 'Stale Cup', 'date_start' => today()->subDays(3)->toDateString(), 'date_end' => today()->subDay()->toDateString()]);
    auditCategory($pastEnd);

    $this->actingAs($user);

    Volt::test('player.events')
        ->assertDontSee('Done Cup')
        ->assertDontSee('Stale Cup')
        ->call('viewTournament', $concluded->id)
        ->assertSet('viewingTournamentId', null);

    $this->get(route('tournaments.register', $concluded))->assertNotFound();
    $this->get(route('tournaments.register', $pastEnd))->assertNotFound();
});

test('a category counts entries, so a doubles pair holds one slot', function () {
    $tournament = auditTournament();
    $doubles = auditCategory($tournament, ['name' => "Men's Doubles", 'team_size' => 2], ['max_slots' => 1]);

    $application = PlayerApplication::create(['tournament_category_id' => $doubles->id, 'status' => 'pending_verification']);
    foreach (['A', 'B'] as $name) {
        ApplicationPlayer::create([
            'application_id' => $application->id,
            'player_id' => Player::create(['name' => "Player {$name}", 'gender' => 'male'])->id,
            'level_at_apply' => 'Beginner',
        ]);
    }

    expect($doubles->isFull())->toBeTrue()
        ->and(fn () => TournamentCategory::lockAndAssertOpenSlot($doubles->id))->toThrow(RuntimeException::class);
});

test('a rejected application frees its slot', function () {
    $tournamentCategory = auditCategory(auditTournament(), [], ['max_slots' => 1]);
    auditEntry($tournamentCategory, 'Rejected Rick', 'rejected');

    expect($tournamentCategory->isFull())->toBeFalse();
});

test('an application cannot be rerouted into a full category', function () {
    $tournament = auditTournament();
    $source = auditCategory($tournament, ['name' => 'Source']);
    $destination = auditCategory($tournament, ['name' => 'Destination'], ['max_slots' => 1]);
    auditEntry($destination, 'Occupant');
    $moving = auditEntry($source, 'Mover');

    expect(fn () => app(RerouteApplication::class)->handle($moving, $destination))
        ->toThrow(InvalidArgumentException::class, 'full');
});

test('the category card opens a roster of every non-rejected entry', function () {
    $tournament = auditTournament();
    $tournamentCategory = auditCategory($tournament);
    auditEntry($tournamentCategory, 'Approved Andy', 'approved');
    auditEntry($tournamentCategory, 'Pending Paul', 'pending_verification');
    auditEntry($tournamentCategory, 'Rejected Rick', 'rejected');

    $this->actingAs(auditAdmin());

    Volt::test('admin.events.show', ['tournament' => $tournament])
        ->assertDontSee('Approved Andy')
        ->call('viewCategory', $tournamentCategory->id)
        ->assertSee('Approved Andy')
        ->assertSee('Pending Paul')
        ->assertDontSee('Rejected Rick')
        ->call('closeCategory')
        ->assertDontSee('Approved Andy');
});

test('reports are only available for concluded events and only to admins', function () {
    $open = auditTournament();
    $concluded = auditTournament(['concluded_at' => now()]);

    $this->actingAs(auditAdmin())
        ->get(route('admin.events.reports', $open))->assertNotFound();
    $this->get(route('admin.events.reports', $concluded))->assertOk();

    $this->actingAs(User::factory()->create(['role' => 'player']))
        ->get(route('admin.events.reports.sales', $concluded))->assertRedirect(route('player.dashboard'));
});

test('the sales report totals verified fees per category and lists a payment ledger', function () {
    $tournament = auditTournament(['concluded_at' => now()]);
    $tournamentCategory = auditCategory($tournament, [], ['registration_fee' => 250]);

    $paid = auditEntry($tournamentCategory, 'Paid Pat');
    PaymentDetail::create([
        'application_id' => $paid->id,
        'date_of_payment_made' => now(),
        'reference_number' => 'GC-12345',
        'payment_status' => 'verified',
        'proof_of_payment' => 'proofs/a.png',
    ]);
    auditEntry($tournamentCategory, 'Unpaid Una', 'payment_pending');
    auditEntry($tournamentCategory, 'Rejected Rick', 'rejected');

    $this->actingAs(auditAdmin());

    $component = Volt::test('admin.events.reports', ['tournament' => $tournament]);
    $sales = $component->instance()->sales;

    expect($sales['totals'])->toMatchArray(['entries' => 2, 'paid_entries' => 1, 'collected' => 250.0, 'outstanding' => 250.0])
        ->and($sales['ledger'])->toHaveCount(1)
        ->and($sales['ledger'][0]['reference_number'])->toBe('GC-12345');

    $component->assertSee('Paid Pat')->assertSee('GC-12345');

    $csv = $this->get(route('admin.events.reports.sales', $tournament))->assertOk()->streamedContent();

    expect($csv)->toContain('GC-12345')->and($csv)->toContain('250.00');
});

test('the participation report lists approved players, summary counts and downloads as CSV', function () {
    $tournament = auditTournament(['concluded_at' => now()]);
    $tournamentCategory = auditCategory($tournament);
    auditEntry($tournamentCategory, 'Approved Andy');
    auditEntry($tournamentCategory, 'Pending Paul', 'pending_verification');

    $this->actingAs(auditAdmin());

    $participation = Volt::test('admin.events.reports', ['tournament' => $tournament])
        ->call('selectReport', 'participation')
        ->assertSee('Approved Andy')
        ->assertDontSee('Pending Paul')
        ->instance()->participation;

    expect($participation['summary'])->toMatchArray(['players' => 1, 'entries' => 1, 'male' => 1, 'new_players' => 1, 'returning_players' => 0]);

    $csv = $this->get(route('admin.events.reports.participation', $tournament))->assertOk()->streamedContent();

    expect($csv)->toContain('Approved Andy')->and($csv)->not->toContain('Pending Paul');
});
