<?php

namespace App\Actions\Applications;

use App\Models\Player;
use App\Models\PlayerApplication;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every player in an entry about a decision on it. Players with no
 * email address (guests who only gave a phone number) are skipped; there is
 * no SMS channel. Mail is queued, so a mail outage never blocks the admin.
 */
class NotifyEntryPlayers
{
    /**
     * @param  class-string<Mailable>  $mailable  takes (PlayerApplication, Player)
     * @return int the number of players who were emailed
     */
    public function handle(PlayerApplication $application, string $mailable): int
    {
        $application->loadMissing([
            'tournamentCategory.tournament',
            'tournamentCategory.category',
            'applicationPlayers.player.user',
        ]);

        $sent = 0;
        $addresses = [];

        foreach ($application->applicationPlayers as $applicationPlayer) {
            $player = $applicationPlayer->player;

            if (! $player instanceof Player) {
                continue;
            }

            $address = $player->email ?: $player->user?->email;
            $key = $address ? mb_strtolower($address) : null;

            if (! $address || in_array($key, $addresses, true)) {
                continue;
            }

            $addresses[] = $key;

            Mail::to($address)->send(new $mailable($application, $player));
            $sent++;
        }

        return $sent;
    }
}
