<?php

namespace App\Support\Scheduling;

use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\TournamentCategory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Generates a category's full single-elimination bracket up front, per
 * NETPLAY.md 4.7: every round's match rows are inserted at once, byes are
 * completed immediately, and winners advance by
 * `next_position = ceil(position / 2)`.
 */
class SingleEliminationScheduler
{
    public const MINIMUM_ENTRIES = 2;

    public const BRACKET_TYPE = 'single_elimination';

    /**
     * Insert the whole bracket for the category's approved entries, seeded in
     * approval order.
     *
     * @return int the number of matches created (byes included)
     *
     * @throws InvalidArgumentException when the draw can't be generated
     */
    public function generate(TournamentCategory $tournamentCategory): int
    {
        return DB::transaction(function () use ($tournamentCategory): int {
            if ($tournamentCategory->hasGeneratedDraw()) {
                throw new InvalidArgumentException('A schedule has already been generated for this category.');
            }

            $entryIds = $tournamentCategory->playerApplications()
                ->where('status', 'approved')
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if (count($entryIds) < self::MINIMUM_ENTRIES) {
                throw new InvalidArgumentException('At least 2 confirmed entries are required to generate a schedule.');
            }

            $size = $this->bracketSize(count($entryIds));
            $seedOrder = $this->seedOrder($size);
            $rounds = (int) log($size, 2);

            $created = 0;
            $firstRound = [];

            for ($round = 1; $round <= $rounds; $round++) {
                for ($position = 1; $position <= $size / (2 ** $round); $position++) {
                    $match = Matches::create([
                        'tournament_category_id' => $tournamentCategory->id,
                        'bracket_type' => self::BRACKET_TYPE,
                        'round' => $round,
                        'position' => $position,
                        'status' => 'scheduled',
                    ]);

                    $created++;

                    if ($round === 1) {
                        $firstRound[$position] = $match;
                    }
                }
            }

            $byes = [];

            foreach ($firstRound as $position => $match) {
                $entries = [];

                foreach ([0, 1] as $index) {
                    $seed = $seedOrder[($position - 1) * 2 + $index];
                    $entries[$index] = $entryIds[$seed - 1] ?? null;

                    if ($entries[$index] !== null) {
                        MatchApplication::create([
                            'match_id' => $match->id,
                            'application_id' => $entries[$index],
                            'slot' => $index + 1,
                        ]);
                    }
                }

                if ($entries[0] === null || $entries[1] === null) {
                    $byes[] = [$match, $entries[0] ?? $entries[1]];
                }
            }

            // Completing a bye advances its lone entry into round 2.
            foreach ($byes as [$match, $applicationId]) {
                $match->update(['status' => 'completed', 'winner_application_id' => $applicationId]);
            }

            return $created;
        });
    }

    /**
     * Put a completed match's winner into its slot in the next round.
     */
    public function advance(Matches $match): void
    {
        if ($match->bracket_type !== self::BRACKET_TYPE || ! $match->winner_application_id) {
            return;
        }

        $next = Matches::query()
            ->where('tournament_category_id', $match->tournament_category_id)
            ->where('round', $match->round + 1)
            ->where('position', (int) ceil($match->position / 2))
            ->first();

        // The final has nowhere to go; a match already underway keeps its
        // competitors rather than changing under the umpire.
        if (! $next || $next->status !== 'scheduled' || $next->games()->exists()) {
            return;
        }

        MatchApplication::updateOrCreate(
            ['match_id' => $next->id, 'slot' => $match->position % 2 === 1 ? 1 : 2],
            ['application_id' => $match->winner_application_id],
        );
    }

    /**
     * The smallest power of two that holds `$entries`.
     */
    public function bracketSize(int $entries): int
    {
        $size = 2;

        while ($size < $entries) {
            $size *= 2;
        }

        return $size;
    }

    /**
     * Seeds in bracket order, so seed 1 meets the last seed and the top seeds
     * meet as late as possible, e.g. 8 => [1, 8, 4, 5, 2, 7, 3, 6]. Seeds
     * above the entry count are byes.
     *
     * @return list<int>
     */
    public function seedOrder(int $size): array
    {
        $order = [1];

        while (count($order) < $size) {
            $total = count($order) * 2;
            $next = [];

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $total + 1 - $seed;
            }

            $order = $next;
        }

        return $order;
    }
}
