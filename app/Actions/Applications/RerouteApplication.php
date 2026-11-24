<?php

namespace App\Actions\Applications;

use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\TournamentCategory;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Moves an application to another category of the same tournament, so an
 * admin can patch uneven entry counts before generating a schedule.
 */
class RerouteApplication
{
    /**
     * Gender/age mismatches only block unless `$overrideEligibility` is
     * set; a team-size mismatch, a different tournament, a full destination,
     * or a started match in either category always blocks.
     *
     * @return array{fee_changed: bool, from_fee: string, to_fee: string}
     *
     * @throws InvalidArgumentException
     */
    public function handle(PlayerApplication $application, TournamentCategory $destination, bool $overrideEligibility = false): array
    {
        $application->loadMissing('tournamentCategory');
        $source = $application->tournamentCategory;

        if ($source->tournament_id !== $destination->tournament_id) {
            throw new InvalidArgumentException('Applications can only be moved within the same tournament.');
        }

        if ($source->id === $destination->id) {
            throw new InvalidArgumentException('The application is already in that category.');
        }

        $issues = $destination->eligibilityIssuesFor($application);

        if ($issues['team_size_mismatch']) {
            throw new InvalidArgumentException('The number of players in this entry does not match the destination category\'s team size.');
        }

        // A draw that hasn't started can still be regenerated to take the
        // newcomer; one that has would lose results.
        if ($destination->hasStartedMatches() || $source->hasStartedMatches()) {
            throw new InvalidArgumentException('A match has already started in one of these categories, so entries can no longer be moved.');
        }

        // A rejected application holds no slot, so only a live one needs room.
        if ($application->status !== 'rejected' && $destination->isFull()) {
            throw new InvalidArgumentException('The destination category is full.');
        }

        if (! $overrideEligibility && $issues['ineligible_players']->isNotEmpty()) {
            throw new InvalidArgumentException('Some players are not eligible for the destination category: '.$this->names($issues['ineligible_players']).'.');
        }

        // A slot in the old category's draw means nothing in the new one.
        $application->update(['tournament_category_id' => $destination->id, 'draw_position' => null]);

        return [
            'fee_changed' => (float) $source->registration_fee !== (float) $destination->registration_fee,
            'from_fee' => (string) $source->registration_fee,
            'to_fee' => (string) $destination->registration_fee,
        ];
    }

    /**
     * @param  Collection<int, Player>  $players
     */
    private function names(Collection $players): string
    {
        return $players->pluck('name')->implode(', ');
    }
}
