<?php

use App\Actions\Applications\RerouteApplication;
use App\Actions\Matches\ResetDraw;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\Matches;
use App\Models\MatchGame;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Support\Scheduling\InsufficientSlotsException;
use App\Support\Scheduling\RoundRobinScheduler;
use App\Support\Scheduling\SingleEliminationScheduler;
use App\Support\Scheduling\TimetableOptions;
use Livewire\Volt\Volt;

function regenTournament(string $format = 'round_robin', array $overrides = []): Tournament
{
    return Tournament::create([
        'name' => 'Regen Open',
        'format' => $format,
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        // 2035-03-03 is a Saturday.
        'date_start' => '2035-03-03',
        'date_end' => '2035-03-11',
        'time' => '9:00 AM - 12:00 PM',
        // Stored in week order, as the event form saves it.
        'specific_days' => 'Sun, Sat',
        'registration_start' => '2035-02-01',
        'registration_end' => '2035-02-28',
        'status' => 'published',
        ...$overrides,
    ]);
}

function regenCategory(Tournament $tournament, int $entries, string $name = "Men's Singles"): TournamentCategory
{
    $category = Category::create(['name' => $name, 'gender' => 'male', 'team_size' => 1]);

    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 32,
        'status' => 'open',
    ]);

    foreach (range(1, $entries) as $number) {
        regenEntry($tournamentCategory, "$name Player $number");
    }

    return $tournamentCategory;
}

function regenEntry(TournamentCategory $tournamentCategory, string $name, string $status = 'approved'): PlayerApplication
{
    $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => $status]);
    ApplicationPlayer::create([
        'application_id' => $application->id,
        'player_id' => Player::create(['name' => $name])->id,
        'level_at_apply' => 'Beginner',
    ]);

    return $application;
}

function regenAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

it('dates a round robin on the event days and inside the time window', function () {
    $tournamentCategory = regenCategory(regenTournament(), 4);

    app(RoundRobinScheduler::class)->generate($tournamentCategory, new TimetableOptions(60, 0, 2));

    $matches = Matches::where('tournament_category_id', $tournamentCategory->id)->get();

    expect($matches)->toHaveCount(6)
        ->and($matches->every(fn ($match) => $match->scheduled_at !== null && $match->court !== null))->toBeTrue()
        ->and($matches->every(fn ($match) => in_array($match->scheduled_at->format('D'), ['Sat', 'Sun'], true)))->toBeTrue()
        ->and($matches->every(fn ($match) => $match->scheduled_at->format('H:i') >= '09:00' && $match->scheduled_at->format('H:i') <= '11:00'))->toBeTrue();

    // 2 courts, 3 one-hour slots on Saturday: all six fit on 3 Mar.
    expect($matches->map(fn ($match) => $match->scheduled_at->format('Y-m-d'))->unique()->all())->toBe(['2035-03-03']);
});

it('never puts a player in two matches at the same time', function () {
    $tournamentCategory = regenCategory(regenTournament(), 5);

    app(RoundRobinScheduler::class)->generate($tournamentCategory, new TimetableOptions(30, 0, 3));

    $byPlayer = [];

    foreach (Matches::with('matchApplications.application.applicationPlayers')->get() as $match) {
        foreach ($match->matchApplications as $matchApplication) {
            foreach ($matchApplication->application->applicationPlayers as $applicationPlayer) {
                $byPlayer[$applicationPlayer->player_id][] = $match->scheduled_at->getTimestamp();
            }
        }
    }

    foreach ($byPlayer as $times) {
        expect($times)->toBe(array_values(array_unique($times)));
    }
});

it('skips a court another category already uses at that time', function () {
    $tournament = regenTournament();
    $first = regenCategory($tournament, 2, 'First');
    $second = regenCategory($tournament, 2, 'Second');

    app(RoundRobinScheduler::class)->generate($first, new TimetableOptions(60, 0, 1));
    app(RoundRobinScheduler::class)->generate($second, new TimetableOptions(60, 0, 1));

    $firstMatch = Matches::where('tournament_category_id', $first->id)->first();
    $secondMatch = Matches::where('tournament_category_id', $second->id)->first();

    expect($secondMatch->scheduled_at->gt($firstMatch->scheduled_at))->toBeTrue();
});

it('refuses to generate when the matches do not fit, and leaves nothing behind', function () {
    // One court, 3 one-hour slots a day, but Saturday only: 6 matches need 2 days.
    $tournament = regenTournament(overrides: ['date_end' => '2035-03-03']);
    $tournamentCategory = regenCategory($tournament, 4);

    expect(fn () => app(RoundRobinScheduler::class)->generate($tournamentCategory, new TimetableOptions(60, 0, 1)))
        ->toThrow(InsufficientSlotsException::class);

    expect($tournamentCategory->hasGeneratedDraw())->toBeFalse();
});

it('can generate anyway and leave the overflow undated', function () {
    $tournament = regenTournament(overrides: ['date_end' => '2035-03-03']);
    $tournamentCategory = regenCategory($tournament, 4);

    app(RoundRobinScheduler::class)->generate($tournamentCategory, new TimetableOptions(60, 0, 1), allowUndated: true);

    $matches = Matches::where('tournament_category_id', $tournamentCategory->id)->get();

    expect($matches)->toHaveCount(6)
        ->and($matches->whereNotNull('scheduled_at'))->toHaveCount(3)
        ->and($matches->whereNull('scheduled_at'))->toHaveCount(3);
});

it('dates a single elimination bracket round by round and skips byes', function () {
    $tournamentCategory = regenCategory(regenTournament('single_elimination'), 3);

    app(SingleEliminationScheduler::class)->generate($tournamentCategory, new TimetableOptions(60, 0, 2));

    $matches = Matches::where('tournament_category_id', $tournamentCategory->id)->orderBy('round')->orderBy('position')->get();
    $roundOne = $matches->where('round', 1);
    $final = $matches->firstWhere('round', 2);

    // 3 entries: one real round-1 match and a BYE; the bye is never dated.
    expect($roundOne->whereNotNull('scheduled_at'))->toHaveCount(1)
        ->and($roundOne->firstWhere('status', 'completed')->scheduled_at)->toBeNull()
        ->and($final->scheduled_at->gte($roundOne->whereNotNull('scheduled_at')->first()->scheduled_at->copy()->addHour()))->toBeTrue();
});

it('refuses auto-dating when the event has no time window', function () {
    $tournamentCategory = regenCategory(regenTournament(overrides: ['time' => null]), 3);

    expect(fn () => app(RoundRobinScheduler::class)->generate($tournamentCategory, new TimetableOptions))
        ->toThrow(InvalidArgumentException::class, 'time window');
});

it('flags a draw as out of date when entries are added or withdrawn', function () {
    $tournament = regenTournament();
    $tournamentCategory = regenCategory($tournament, 3);

    app(RoundRobinScheduler::class)->generate($tournamentCategory);

    expect($tournamentCategory->isDrawOutOfDate())->toBeFalse();

    $newcomer = regenEntry($tournamentCategory, 'Newcomer');
    expect($tournamentCategory->isDrawOutOfDate())->toBeTrue()
        ->and($tournamentCategory->drawEntryChanges())->toBe(['added' => 1, 'removed' => 0]);

    $newcomer->update(['status' => 'rejected']);
    expect($tournamentCategory->isDrawOutOfDate())->toBeFalse();

    $tournamentCategory->playerApplications()->first()->update(['status' => 'rejected']);
    expect($tournamentCategory->drawEntryChanges())->toBe(['added' => 0, 'removed' => 1]);
});

it('reroutes into a drawn category and marks it out of date', function () {
    $tournament = regenTournament();
    $destination = regenCategory($tournament, 3, 'Destination');
    $source = regenCategory($tournament, 1, 'Source');

    app(RoundRobinScheduler::class)->generate($destination);

    app(RerouteApplication::class)->handle($source->playerApplications()->first(), $destination, true);

    expect($destination->isDrawOutOfDate())->toBeTrue()
        ->and($destination->drawEntryChanges()['added'])->toBe(1);
});

it('blocks rerouting into a category with a started match', function () {
    $tournament = regenTournament();
    $destination = regenCategory($tournament, 3, 'Destination');
    $source = regenCategory($tournament, 1, 'Source');

    app(RoundRobinScheduler::class)->generate($destination);
    $match = Matches::where('tournament_category_id', $destination->id)->first();
    MatchGame::create(['match_id' => $match->id, 'game_number' => 1]);

    expect(fn () => app(RerouteApplication::class)->handle($source->playerApplications()->first(), $destination, true))
        ->toThrow(InvalidArgumentException::class, 'already started');
});

it('regenerates a round robin with the new entry included', function () {
    $tournamentCategory = regenCategory(regenTournament(), 3);
    app(RoundRobinScheduler::class)->generate($tournamentCategory);
    regenEntry($tournamentCategory, 'Latecomer');

    Volt::actingAs(regenAdmin())
        ->test('admin.events.schedule.show', ['tournament' => $tournamentCategory->tournament, 'tournamentCategory' => $tournamentCategory])
        ->assertSee('Entries changed since this schedule was generated')
        ->call('regenerate')
        ->assertDontSee('Entries changed since this schedule was generated')
        ->set('autoDate', false)
        ->call('generate');

    expect(Matches::where('tournament_category_id', $tournamentCategory->id)->count())->toBe(6);
});

it('keeps the admin arrangement when a single elimination bracket is regenerated', function () {
    $tournamentCategory = regenCategory(regenTournament('single_elimination'), 4);
    $scheduler = app(SingleEliminationScheduler::class);

    $scheduler->swap($tournamentCategory, 0, 3);
    $arranged = $scheduler->arrangement($tournamentCategory);
    $scheduler->generate($tournamentCategory);

    $newcomer = regenEntry($tournamentCategory, 'Fifth');

    app(ResetDraw::class)->handle($tournamentCategory);

    expect($tournamentCategory->hasGeneratedDraw())->toBeFalse();

    $after = $scheduler->arrangement($tournamentCategory);

    // The first four placements survive; the newcomer takes a free slot.
    foreach ($arranged as $index => $id) {
        expect($after[$index])->toBe($id);
    }

    expect($after)->toContain($newcomer->id);
});

it('refuses to regenerate once a match has started', function () {
    $tournamentCategory = regenCategory(regenTournament(), 3);
    app(RoundRobinScheduler::class)->generate($tournamentCategory);

    $match = Matches::where('tournament_category_id', $tournamentCategory->id)->first();
    $match->update(['status' => 'in_progress']);

    expect(fn () => app(ResetDraw::class)->handle($tournamentCategory))
        ->toThrow(InvalidArgumentException::class, 'already started');

    expect($tournamentCategory->hasGeneratedDraw())->toBeTrue();
});

it('does not count a completed bye as a started match', function () {
    $tournamentCategory = regenCategory(regenTournament('single_elimination'), 3);
    app(SingleEliminationScheduler::class)->generate($tournamentCategory);

    expect($tournamentCategory->hasStartedMatches())->toBeFalse();

    app(ResetDraw::class)->handle($tournamentCategory);

    expect($tournamentCategory->hasGeneratedDraw())->toBeFalse();
});

it('clears every draw when an unpublished event changes its dates', function () {
    $tournament = regenTournament(overrides: ['status' => 'draft']);
    $tournamentCategory = regenCategory($tournament, 3);
    app(RoundRobinScheduler::class)->generate($tournamentCategory);

    Volt::actingAs(regenAdmin())
        ->test('admin.events.edit', ['tournament' => $tournament])
        ->set('dateEnd', '2035-03-18')
        ->call('save')
        ->assertHasNoErrors();

    expect($tournamentCategory->hasGeneratedDraw())->toBeFalse()
        ->and($tournament->fresh()->date_end->format('Y-m-d'))->toBe('2035-03-18');
});

it('keeps the draws when an unpublished event changes something unrelated', function () {
    $tournament = regenTournament(overrides: ['status' => 'draft']);
    $tournamentCategory = regenCategory($tournament, 3);
    app(RoundRobinScheduler::class)->generate($tournamentCategory);

    Volt::actingAs(regenAdmin())
        ->test('admin.events.edit', ['tournament' => $tournament])
        ->set('name', 'Renamed Open')
        ->call('save')
        ->assertHasNoErrors();

    expect($tournamentCategory->hasGeneratedDraw())->toBeTrue()
        ->and($tournament->fresh()->name)->toBe('Renamed Open');
});

it('refuses a date change when a match has already started', function () {
    $tournament = regenTournament(overrides: ['status' => 'draft']);
    $tournamentCategory = regenCategory($tournament, 3);
    app(RoundRobinScheduler::class)->generate($tournamentCategory);
    Matches::where('tournament_category_id', $tournamentCategory->id)->first()->update(['status' => 'in_progress']);

    Volt::actingAs(regenAdmin())
        ->test('admin.events.edit', ['tournament' => $tournament])
        ->set('dateEnd', '2035-03-18')
        ->call('save')
        ->assertHasErrors('dateStart');

    expect($tournament->fresh()->date_end->format('Y-m-d'))->toBe('2035-03-11')
        ->and($tournamentCategory->hasGeneratedDraw())->toBeTrue();
});

it('shows the overflow option on the schedule page when matches do not fit', function () {
    $tournament = regenTournament(overrides: ['date_end' => '2035-03-03']);
    $tournamentCategory = regenCategory($tournament, 4);

    Volt::actingAs(regenAdmin())
        ->test('admin.events.schedule.show', ['tournament' => $tournament, 'tournamentCategory' => $tournamentCategory])
        ->set('matchDuration', '60')
        ->set('courtCount', '1')
        ->call('generate')
        ->assertSet('canGenerateUndated', true)
        ->call('generateWithUndatedOverflow')
        ->assertSet('canGenerateUndated', false);

    expect(Matches::where('tournament_category_id', $tournamentCategory->id)->whereNull('scheduled_at')->count())->toBe(3);
});

it('seeds the regeneration demo events in the expected states', function () {
    $this->seed(Database\Seeders\RegenerateDemoSeeder::class);

    $byName = fn (string $event, string $category) => TournamentCategory::whereHas('tournament', fn ($q) => $q->where('name', $event))
        ->whereHas('category', fn ($q) => $q->where('name', $category))->firstOrFail();

    $mens = $byName('Round Robin Regen Demo', "Men's Singles");
    $doubles = $byName('Round Robin Regen Demo', "Men's Doubles");

    expect($mens->isDrawOutOfDate())->toBeTrue()
        ->and($mens->matches()->whereNull('scheduled_at')->count())->toBe(0)
        ->and($byName('Round Robin Regen Demo', "Women's Singles")->isDrawOutOfDate())->toBeTrue()
        ->and($byName('Round Robin Regen Demo', 'Mixed Doubles')->hasGeneratedDraw())->toBeFalse()
        ->and($doubles->hasStartedMatches())->toBeTrue()
        ->and($byName('Single Elim Regen Demo', "Men's Singles")->isDrawOutOfDate())->toBeTrue();
});
