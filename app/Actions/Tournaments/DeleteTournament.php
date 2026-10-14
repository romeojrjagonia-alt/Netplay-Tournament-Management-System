<?php

namespace App\Actions\Tournaments;

use App\Mail\EventCancelled;
use App\Models\PaymentDetail;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Hard-deletes an event along with its uploaded files, then emails players
 * who had a live application.
 *
 * Only events with no history can be deleted. A concluded event is
 * archived, and one with finalized records (see
 * Tournament::hasFinalizedRecords) holds player history, so both are
 * refused: deleting them would erase match history and leave
 * `player_statistics` out of step, since the database cascade bypasses the
 * match observer.
 */
class DeleteTournament
{
    /**
     * @throws InvalidArgumentException
     */
    public function handle(Tournament $tournament): void
    {
        if ($tournament->is_concluded) {
            throw new InvalidArgumentException('A concluded event is archived and cannot be deleted.');
        }

        if ($tournament->hasFinalizedRecords()) {
            throw new InvalidArgumentException('This event has finalized entries or played matches, so it cannot be deleted.');
        }

        $recipients = $this->recipients($tournament);
        $files = $this->uploadedFiles($tournament);

        DB::transaction(fn () => $tournament->delete());

        Storage::disk('public')->delete($files);

        foreach ($recipients as $address => $playerName) {
            Mail::to($address)->send(new EventCancelled($playerName, $tournament->name));
        }
    }

    /**
     * One email per address across every non-rejected application, keyed by
     * address with the player's name. Guests with no email are skipped.
     *
     * @return array<string, string>
     */
    private function recipients(Tournament $tournament): array
    {
        $applications = PlayerApplication::query()
            ->whereIn('tournament_category_id', $tournament->tournamentCategories()->select('id'))
            ->where('status', '!=', 'rejected')
            ->with('applicationPlayers.player.user')
            ->get();

        $recipients = [];

        foreach ($applications->flatMap->applicationPlayers as $applicationPlayer) {
            $player = $applicationPlayer->player;
            $address = $player?->email ?: $player?->user?->email;

            if ($address) {
                $recipients[mb_strtolower($address)] ??= $player->name;
            }
        }

        return $recipients;
    }

    /**
     * @return list<string>
     */
    private function uploadedFiles(Tournament $tournament): array
    {
        $proofs = PaymentDetail::query()
            ->whereHas('application', fn ($query) => $query->whereIn('tournament_category_id', $tournament->tournamentCategories()->select('id')))
            ->whereNotNull('proof_of_payment')
            ->pluck('proof_of_payment')
            ->all();

        return array_values(array_filter([$tournament->poster_path, ...$proofs]));
    }
}
