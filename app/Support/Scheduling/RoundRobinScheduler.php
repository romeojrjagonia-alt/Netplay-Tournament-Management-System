<?php

namespace App\Support\Scheduling;

use App\Models\MatchApplication;
use App\Models\Matches;
use App\Models\TournamentCategory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Generates a category's full round-robin fixture list up front, per
 * NETPLAY.md 4.7: every match row is inserted at once, with no court, time
 * or umpire assigned yet.
 */
class RoundRobinScheduler
{
    public const MINIMUM_ENTRIES = 2;

    /**
     * Insert every pairing for the category's approved entries, in approval
     * order.
     *
     * With `$timetable`, every match is also given a date, time and court from
     * the event's schedule. If they don't all fit, nothing is generated unless
     * `$allowUndated` lets the overflow stay undated.
     *
     * @return int the number of matches created
     *
     * @throws InvalidArgumentException when the draw can't be generated
     * @throws InsufficientSlotsException when the matches don't fit the event's time slots
     */
    public function generate(TournamentCategory $tournamentCategory, ?TimetableOptions $timetable = null, bool $allowUndated = false): int
    {
        return DB::transaction(function () use ($tournamentCategory, $timetable, $allowUndated): int {
            // Re-checked inside the transaction so a double-click can't
            // generate the same draw twice.
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

            $created = 0;

            foreach ($this->rounds($entryIds) as $roundIndex => $pairings) {
                foreach ($pairings as $positionIndex => [$firstId, $secondId]) {
                    $match = Matches::create([
                        'tournament_category_id' => $tournamentCategory->id,
                        'bracket_type' => 'round_robin',
                        'round' => $roundIndex + 1,
                        'position' => $positionIndex + 1,
                        'status' => 'scheduled',
                    ]);

                    foreach ([$firstId, $secondId] as $applicationId) {
                        MatchApplication::create([
                            'match_id' => $match->id,
                            'application_id' => $applicationId,
                        ]);
                    }

                    $created++;
                }
            }

            if ($timetable !== null) {
                $unplaced = app(MatchTimetable::class)->assign($tournamentCategory, $timetable);

                if ($unplaced > 0 && ! $allowUndated) {
                    throw new InsufficientSlotsException($unplaced, $created);
                }
            }

            return $created;
        });
    }

    /**
     * The circle method: fix the first entry, rotate the rest one step per
     * round. With an odd count a placeholder is added, and whoever draws it
     * rests that round — no match row is created for a rest.
     *
     * @param  list<int>  $entryIds
     * @return list<list<array{0: int, 1: int}>> pairings per round
     */
    public function rounds(array $entryIds): array
    {
        $slots = $entryIds;

        if (count($slots) % 2 === 1) {
            $slots[] = null;
        }

        $count = count($slots);
        $rounds = [];

        for ($round = 0; $round < $count - 1; $round++) {
            $pairings = [];

            for ($i = 0; $i < $count / 2; $i++) {
                $first = $slots[$i];
                $second = $slots[$count - 1 - $i];

                if ($first !== null && $second !== null) {
                    $pairings[] = [$first, $second];
                }
            }

            $rounds[] = $pairings;

            // Keep slot 0 in place and rotate the others clockwise.
            array_splice($slots, 1, 0, [array_pop($slots)]);
        }

        return $rounds;
    }
}
