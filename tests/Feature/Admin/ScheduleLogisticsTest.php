<?php

use App\Mail\MatchScheduled;
use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

function logisticsMatch(): Matches
{
    $tournament = Tournament::create([
        'name' => 'Audit Open',
        'format' => 'round_robin',
        'sport' => 'Badminton',
        'scoring_system' => 'Best of 3 to 21',
        'date_start' => '2026-10-20',
        'date_end' => '2026-10-22',
        'registration_start' => '2026-10-01',
        'registration_end' => '2026-10-18',
        'status' => 'published',
    ]);

    $category = Category::create(['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1]);

    $tournamentCategory = TournamentCategory::create([
        'tournament_id' => $tournament->id,
        'category_id' => $category->id,
        'registration_fee' => 250,
        'max_slots' => 16,
        'status' => 'open',
    ]);

    $match = Matches::create([
        'tournament_category_id' => $tournamentCategory->id,
        'bracket_type' => 'round_robin',
        'round' => 1,
        'position' => 1,
        'status' => 'scheduled',
    ]);

    foreach ([['Mark Santos', 'mark@example.test'], ['Juan Torres', null]] as [$name, $email]) {
        $player = Player::create(['name' => $name, 'email' => $email]);
        $application = PlayerApplication::create(['tournament_category_id' => $tournamentCategory->id, 'status' => 'approved']);
        ApplicationPlayer::create(['application_id' => $application->id, 'player_id' => $player->id, 'level_at_apply' => 'Beginner']);
        MatchApplication::create(['match_id' => $match->id, 'application_id' => $application->id]);
    }

    return $match;
}

function logisticsPage(Matches $match, ?User $admin = null)
{
    $tournamentCategory = $match->tournamentCategory;

    return Volt::actingAs($admin ?? User::factory()->create(['role' => 'admin']))
        ->test('admin.events.schedule.show', [
            'tournament' => $tournamentCategory->tournament,
            'tournamentCategory' => $tournamentCategory,
        ]);
}

it('lets an admin set a match date, time, court and umpire', function () {
    $match = logisticsMatch();
    $umpire = User::factory()->create(['role' => 'umpire']);

    logisticsPage($match)
        ->call('editMatch', $match->id)
        ->set('editDate', '2026-10-20')
        ->set('editTime', '09:30')
        ->set('editCourt', '3')
        ->set('editUmpireId', $umpire->id)
        ->call('saveMatch')
        ->assertHasNoErrors()
        ->assertSet('editingMatchId', null);

    $match->refresh();

    expect($match->scheduled_at->format('Y-m-d H:i'))->toBe('2026-10-20 09:30')
        ->and($match->court)->toBe('3')
        ->and($match->assigned_umpire_id)->toBe($umpire->id);
});

it('requires a time when a date is chosen and a real umpire', function () {
    $match = logisticsMatch();
    $notAnUmpire = User::factory()->create(['role' => 'player']);

    logisticsPage($match)
        ->call('editMatch', $match->id)
        ->set('editDate', '2026-10-20')
        ->set('editUmpireId', $notAnUmpire->id)
        ->call('saveMatch')
        ->assertHasErrors(['editTime', 'editUmpireId']);
});

it('forbids non-admins from the schedule page', function () {
    $match = logisticsMatch();

    logisticsPage($match, User::factory()->create(['role' => 'player']))->assertForbidden();
});

it('emails each player once about a scheduled match', function () {
    Mail::fake();
    $match = logisticsMatch();
    $match->update(['scheduled_at' => '2026-10-20 09:30:00', 'court' => '3']);

    logisticsPage($match)
        ->call('notifyPlayers')
        ->assertDispatched('system-message', message: 'Sent 1 email for 1 match. 1 player had no email address on file.');

    Mail::assertSent(MatchScheduled::class, 1);
    Mail::assertSent(MatchScheduled::class, fn (MatchScheduled $mail) => $mail->hasTo('mark@example.test'));
    expect($match->fresh()->schedule_notified_at)->not->toBeNull();

    logisticsPage($match)->call('notifyPlayers')->assertDispatched('system-message', variant: 'danger');

    Mail::assertSent(MatchScheduled::class, 1);
});

it('re-announces a match whose time changed', function () {
    Mail::fake();
    $match = logisticsMatch();
    $match->update(['scheduled_at' => '2026-10-20 09:30:00', 'schedule_notified_at' => now()]);

    logisticsPage($match)
        ->call('editMatch', $match->id)
        ->set('editTime', '10:30')
        ->call('saveMatch')
        ->call('notifyPlayers');

    Mail::assertSent(MatchScheduled::class, 1);
});

it('renders the scheduled email', function () {
    $match = logisticsMatch();
    $match->update(['scheduled_at' => '2026-10-20 09:30:00', 'court' => '3']);
    $player = Player::where('name', 'Mark Santos')->first();

    $html = (new MatchScheduled($match->load('tournamentCategory.tournament', 'matchApplications.application.applicationPlayers.player'), $player))->render();

    expect($html)->toContain('Audit Open')->toContain('9:30 AM')->toContain('Juan Torres')->toContain('3');
});
