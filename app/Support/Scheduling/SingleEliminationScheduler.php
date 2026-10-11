<?php

namespace App\Support\Scheduling;

use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\PlayerApplication;
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
     * Insert the whole bracket for the category's approved entries. Entries an
     * admin has placed (`draw_position`) go exactly where they were put; the
     * rest are seeded in approval order into the remaining slots.
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

            $slots = $this->arrangement($tournamentCategory);
            $entryCount = count(array_filter($slots, fn (?int $id): bool => $id !== null));

            if ($entryCount < self::MINIMUM_ENTRIES) {
                throw new InvalidArgumentException('At least 2 confirmed entries are required to generate a schedule.');
            }

            $size = count($slots);
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
                    $entries[$index] = $slots[($position - 1) * 2 + $index];

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
     * The round-1 line-up: one application id (or null for a BYE) per slot,
     * slots `2n` and `2n + 1` meeting in match `n + 1`. Placed entries keep
     * their `draw_position`; everyone else fills the remaining slots in
     * seed order, which with nothing placed is the default seeded bracket.
     *
     * @return list<int|null>
     */
    public function arrangement(TournamentCategory $tournamentCategory): array
    {
        $entries = $tournamentCategory->playerApplications()
            ->where('status', 'approved')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'draw_position']);

        if ($entries->count() < self::MINIMUM_ENTRIES) {
            return $entries->pluck('id')->all();
        }

        $size = $this->bracketSize($entries->count());
        $slots = array_fill(0, $size, null);
        $unplaced = [];

        foreach ($entries as $entry) {
            $index = $entry->draw_position === null ? -1 : $entry->draw_position - 1;

            if ($index >= 0 && $index < $size && $slots[$index] === null) {
                $slots[$index] = $entry->id;
            } else {
                $unplaced[] = $entry->id;
            }
        }

        $seedOrder = $this->seedOrder($size);

        for ($seed = 1; $seed <= $size && $unplaced !== []; $seed++) {
            $index = array_search($seed, $seedOrder, true);

            if ($slots[$index] === null) {
                $slots[$index] = array_shift($unplaced);
            }
        }

        return $slots;
    }

    /**
     * Swap the contents of two round-1 slots (0-based) and remember the whole
     * line-up. Either slot may be a BYE, which moves an entry into or out of
     * one.
     *
     * @throws InvalidArgumentException when the draw is already generated or a slot is out of range
     */
    public function swap(TournamentCategory $tournamentCategory, int $from, int $to): void
    {
        DB::transaction(function () use ($tournamentCategory, $from, $to): void {
            if ($tournamentCategory->hasGeneratedDraw()) {
                throw new InvalidArgumentException('The bracket is locked because the schedule has already been generated.');
            }

            $slots = $this->arrangement($tournamentCategory);

            if (! array_key_exists($from, $slots) || ! array_key_exists($to, $slots)) {
                throw new InvalidArgumentException('That slot is not part of the bracket.');
            }

            [$slots[$from], $slots[$to]] = [$slots[$to], $slots[$from]];

            foreach ($slots as $index => $applicationId) {
                if ($applicationId !== null) {
                    PlayerApplication::whereKey($applicationId)->update(['draw_position' => $index + 1]);
                }
            }
        });
    }

    /**
     * Forget the admin's arrangement and go back to the seeded default.
     *
     * @throws InvalidArgumentException when the draw is already generated
     */
    public function resetArrangement(TournamentCategory $tournamentCategory): void
    {
        if ($tournamentCategory->hasGeneratedDraw()) {
            throw new InvalidArgumentException('The bracket is locked because the schedule has already been generated.');
        }

        $tournamentCategory->playerApplications()->update(['draw_position' => null]);
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
