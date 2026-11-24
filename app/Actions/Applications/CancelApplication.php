<?php

namespace App\Actions\Applications;

use App\Mail\ApplicationCancelled;
use App\Models\Player;
use App\Models\PlayerApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Hard-deletes a pending application that has no payment made yet, along
 * with its uploaded ID photos and any rejected payment proofs, then emails
 * the other players on the entry. Deleting frees the slot and lets every
 * player on it apply again.
 */
class CancelApplication
{
    /**
     * @param  Player|null  $cancelledBy  the player who cancelled; null when staff cancelled for the entry
     *
     * @throws InvalidArgumentException
     */
    public function handle(PlayerApplication $application, ?Player $cancelledBy = null): void
    {
        $notice = null;
        $files = [];

        DB::transaction(function () use ($application, $cancelledBy, &$notice, &$files): void {
            // Re-check under a lock so a payment proof uploaded a moment ago can't be orphaned.
            $locked = PlayerApplication::query()
                ->with(['tournamentCategory.tournament', 'tournamentCategory.category', 'applicationPlayers.player.user', 'paymentDetails'])
                ->lockForUpdate()
                ->findOrFail($application->id);

            if (! $locked->canBeCancelled()) {
                throw new InvalidArgumentException('This application can no longer be cancelled.');
            }

            $notice = [
                'tournament' => $locked->tournamentCategory->tournament->name,
                'category' => $locked->tournamentCategory->category_label,
                'recipients' => $this->recipients($locked, $cancelledBy),
            ];

            $files = $locked->applicationPlayers->pluck('id_document_path')
                ->merge($locked->paymentDetails->pluck('proof_of_payment'))
                ->filter()
                ->values()
                ->all();

            $locked->delete();
        });

        Storage::disk('public')->delete($files);

        foreach ($notice['recipients'] as $address => $playerName) {
            try {
                Mail::to($address)->send(new ApplicationCancelled($playerName, $notice['tournament'], $notice['category']));
            } catch (\Throwable $e) {
                // The cancellation is already done; a mail problem must not undo or fail it.
                report($e);
            }
        }
    }

    /**
     * One email per address across everyone on the entry except whoever
     * cancelled. Players with no email (walk-ins who gave a phone) are skipped.
     *
     * @return array<string, string> address => player name
     */
    private function recipients(PlayerApplication $application, ?Player $cancelledBy): array
    {
        $recipients = [];

        foreach ($application->applicationPlayers as $applicationPlayer) {
            $player = $applicationPlayer->player;

            if (! $player || $player->is($cancelledBy)) {
                continue;
            }

            $address = $player->user?->email ?: $player->email;

            if ($address) {
                $recipients[mb_strtolower($address)] ??= $player->name;
            }
        }

        return $recipients;
    }
}
