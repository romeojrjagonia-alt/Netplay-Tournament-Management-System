<?php

namespace App\Actions\Matches;

use App\Mail\MatchScheduled;
use App\Models\Matches;
use App\Models\Player;
use App\Models\TournamentCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every player in a category whose match has a date and time that
 * they have not yet been told about (or that changed since they were).
 */
class NotifyPlayersOfSchedule
{
    /**
     * @return array{matches: int, emails: int, without_email: int}
     */
    public function handle(TournamentCategory $tournamentCategory): array
    {
        $matches = $tournamentCategory->matches()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereNull('schedule_notified_at')
            ->has('matchApplications', '>=', 2)
            ->with([
                'tournamentCategory.tournament',
                'tournamentCategory.category',
                'matchApplications.application.applicationPlayers.player.user',
            ])
            ->get();

        $emails = 0;
        $withoutEmail = 0;

        foreach ($matches as $match) {
            foreach ($this->playersOf($match) as $player) {
                $address = $player->email ?: $player->user?->email;

                if (! $address) {
                    $withoutEmail++;

                    continue;
                }

                Mail::to($address)->send(new MatchScheduled($match, $player));
                $emails++;
            }

            $match->update(['schedule_notified_at' => now()]);
        }

        return ['matches' => $matches->count(), 'emails' => $emails, 'without_email' => $withoutEmail];
    }

    /**
     * @return Collection<int, Player>
     */
    private function playersOf(Matches $match)
    {
        return $match->matchApplications
            ->flatMap(fn ($matchApplication) => $matchApplication->application?->applicationPlayers ?? [])
            ->map(fn ($applicationPlayer) => $applicationPlayer->player)
            ->filter()
            ->unique('id');
    }
}
