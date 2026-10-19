<?php

namespace App\Support\Scheduling;

use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Hands out dates, times and courts to a freshly generated draw from the
 * event's own date range, daily time window and days of the week.
 *
 * Slots are filled in order. A player is never in two overlapping matches,
 * and a player's back-to-back matches are avoided when anything else fits;
 * courts and players already booked by the tournament's other categories are
 * respected. Single elimination goes round by round, so a round never starts
 * before the previous one has finished.
 */
class MatchTimetable
{
    /**
     * Date every schedulable match in the category.
     *
     * @return int how many matches found no slot and were left undated
     *
     * @throws InvalidArgumentException when the event has no usable time window or days
     */
    public function assign(TournamentCategory $tournamentCategory, TimetableOptions $options, ?CarbonInterface $notBefore = null): int
    {
        $tournamentCategory->loadMissing('tournament');
        $tournament = $tournamentCategory->tournament;

        $times = $this->slotsByTime($tournament, $options, $notBefore ?? now());

        $matches = $tournamentCategory->matches()
            ->with('matchApplications.application.applicationPlayers')
            ->orderBy('round')
            ->orderBy('position')
            ->get()
            // A BYE is already completed with a lone entry and is never played.
            ->reject(fn (Matches $match): bool => $match->status === 'completed' && $match->matchApplications->count() < 2);

        $roundByRound = $tournament->format === 'single_elimination';
        $groups = $roundByRound ? $matches->groupBy('round')->values() : collect([$matches->values()]);

        $duration = $options->durationMinutes * 60;
        $step = ($options->durationMinutes + $options->breakMinutes) * 60;

        [$courtStarts, $unnamedStarts, $playerStarts] = $this->existingBookings($tournamentCategory);

        $unplaced = 0;
        $notBeforeTimestamp = null;

        foreach ($groups as $position => $queue) {
            $queue = $queue->values()->all();
            $roundEnd = $notBeforeTimestamp;

            foreach ($times as $timestamp => $courts) {
                if ($queue === []) {
                    break;
                }

                if ($notBeforeTimestamp !== null && $timestamp < $notBeforeTimestamp) {
                    continue;
                }

                $free = array_values(array_filter(
                    $courts,
                    fn (string $court): bool => ! $this->overlaps($courtStarts[$court] ?? [], $timestamp, $duration),
                ));

                // Matches booked elsewhere with no court named still use one.
                $unnamed = count(array_filter($unnamedStarts, fn (int $start): bool => abs($start - $timestamp) < $duration));
                $free = array_slice($free, 0, max(0, count($free) - $unnamed));

                foreach ($free as $court) {
                    $index = $this->pick($queue, $timestamp, $duration, $step, $playerStarts);

                    if ($index === null) {
                        continue;
                    }

                    $match = $queue[$index];
                    array_splice($queue, $index, 1);

                    $match->update(['scheduled_at' => Carbon::createFromTimestamp($timestamp, config('app.timezone')), 'court' => $court]);

                    $courtStarts[$court][] = $timestamp;

                    foreach ($this->playerIds($match) as $playerId) {
                        $playerStarts[$playerId][] = $timestamp;
                    }

                    $roundEnd = max($roundEnd ?? 0, $timestamp + $duration);
                }
            }

            $unplaced += count($queue);

            if ($roundByRound && $queue !== []) {
                // Later rounds can't be dated before this one finishes.
                $unplaced += $groups->slice($position + 1)->sum(fn (Collection $later): int => $later->count());

                break;
            }

            $notBeforeTimestamp = $roundEnd;
        }

        return $unplaced;
    }

    /**
     * Every court-slot the event offers, grouped by start time (a Unix
     * timestamp) in chronological order: each allowed day within the date
     * range, each back-to-back slot that fits the daily time window.
     *
     * @return array<int, list<string>> start timestamp => court names
     *
     * @throws InvalidArgumentException when the event has no time window or days
     */
    public function slotsByTime(Tournament $tournament, TimetableOptions $options, CarbonInterface $notBefore): array
    {
        [$from, $to] = $this->timeWindow($tournament);
        $days = $this->weekdays($tournament);

        $courts = array_map('strval', range(1, max(1, $options->courts)));
        $slots = [];

        for ($date = $tournament->date_start->copy()->startOfDay(); $date->lte($tournament->date_end); $date->addDay()) {
            if (! in_array($date->format('D'), $days, true)) {
                continue;
            }

            for ($minute = $from; $minute + $options->durationMinutes <= $to; $minute += $options->durationMinutes + $options->breakMinutes) {
                $start = $date->copy()->addMinutes($minute);

                if ($start->lt($notBefore)) {
                    continue;
                }

                $slots[$start->getTimestamp()] = $courts;
            }
        }

        ksort($slots);

        return $slots;
    }

    /**
     * The daily window as minutes after midnight, parsed from the event's
     * "9:00 AM - 5:00 PM" label.
     *
     * @return array{0: int, 1: int}
     */
    private function timeWindow(Tournament $tournament): array
    {
        [$startLabel, $endLabel] = array_pad(explode(' - ', (string) $tournament->time, 2), 2, null);

        if (! $startLabel || ! $endLabel) {
            throw new InvalidArgumentException('Set a daily time window on the event before dates and times can be assigned automatically.');
        }

        $start = Carbon::parse($startLabel);
        $end = Carbon::parse($endLabel);

        return [$start->hour * 60 + $start->minute, $end->hour * 60 + $end->minute];
    }

    /**
     * @return list<string> weekday abbreviations such as "Sat"
     */
    private function weekdays(Tournament $tournament): array
    {
        $days = array_values(array_filter(array_map('trim', explode(',', (string) $tournament->specific_days))));

        if ($days === []) {
            throw new InvalidArgumentException('Choose the days of the week on the event before dates and times can be assigned automatically.');
        }

        return $days;
    }

    /**
     * What the tournament's other categories already occupy.
     *
     * @return array{0: array<string, list<int>>, 1: list<int>, 2: array<int, list<int>>} court starts by court, starts of matches with no court, and match starts by player id
     */
    private function existingBookings(TournamentCategory $tournamentCategory): array
    {
        $courtStarts = [];
        $unnamedStarts = [];
        $playerStarts = [];

        $others = Matches::query()
            ->whereHas('tournamentCategory', fn ($query) => $query
                ->where('tournament_id', $tournamentCategory->tournament_id)
                ->where('id', '!=', $tournamentCategory->id))
            ->whereNotNull('scheduled_at')
            ->with('matchApplications.application.applicationPlayers')
            ->get();

        foreach ($others as $match) {
            $timestamp = $match->scheduled_at->getTimestamp();

            if ($match->court === null || $match->court === '') {
                $unnamedStarts[] = $timestamp;
            } else {
                $courtStarts[$match->court][] = $timestamp;
            }

            foreach ($this->playerIds($match) as $playerId) {
                $playerStarts[$playerId][] = $timestamp;
            }
        }

        return [$courtStarts, $unnamedStarts, $playerStarts];
    }

    /**
     * The first queued match whose players are free at `$timestamp`: one who
     * isn't also playing in the neighbouring slot if possible, otherwise any
     * whose players merely aren't mid-match.
     *
     * @param  list<Matches>  $queue
     * @param  array<int, list<int>>  $playerStarts
     */
    private function pick(array $queue, int $timestamp, int $duration, int $step, array $playerStarts): ?int
    {
        foreach ([$step, $duration - 1] as $reach) {
            foreach ($queue as $index => $match) {
                $busy = false;

                foreach ($this->playerIds($match) as $playerId) {
                    foreach ($playerStarts[$playerId] ?? [] as $start) {
                        if (abs($start - $timestamp) <= $reach) {
                            $busy = true;

                            break 2;
                        }
                    }
                }

                if (! $busy) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $starts
     */
    private function overlaps(array $starts, int $timestamp, int $duration): bool
    {
        foreach ($starts as $start) {
            if (abs($start - $timestamp) < $duration) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function playerIds(Matches $match): array
    {
        return $match->matchApplications
            ->flatMap(fn ($matchApplication) => $matchApplication->application?->applicationPlayers->pluck('player_id') ?? [])
            ->unique()
            ->values()
            ->all();
    }
}
