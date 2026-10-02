<?php

namespace App\Support\Standings;

use App\Models\Matches;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use Illuminate\Support\Collection;

/**
 * Round-robin standings: wins decide first, then the tournament's configured
 * tiebreaker chain breaks ties within each group of equal wins. Entries still
 * level after the whole chain are flagged `tied` rather than guessed at.
 */
class RoundRobinStandings
{
    /**
     * @var Collection<int, Matches>
     */
    private Collection $completedMatches;

    /**
     * Wins/losses/point differential keyed by application id.
     *
     * @var array<int, array{wins: int, losses: int, point_diff: int}>
     */
    private array $totals = [];

    /**
     * @return list<array{application: PlayerApplication, name: string, wins: int, losses: int, point_diff: int, rank: int, tied: bool}>
     */
    public function for(TournamentCategory $tournamentCategory): array
    {
        $tournamentCategory->loadMissing('tournament');

        $applications = $tournamentCategory->playerApplications()
            ->where('status', 'approved')
            ->with('applicationPlayers.player')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $this->completedMatches = $tournamentCategory->matches()
            ->where('status', 'completed')
            ->whereNotNull('winner_application_id')
            ->with(['games.rallies', 'matchApplications.application.applicationPlayers.player'])
            ->get();

        $this->totals = $applications->mapWithKeys(fn (PlayerApplication $application) => [
            $application->id => ['wins' => 0, 'losses' => 0, 'point_diff' => 0],
        ])->all();

        foreach ($this->completedMatches as $match) {
            $this->tally($match);
        }

        $rules = array_values(array_filter(
            $tournamentCategory->tournament->tiebreaker_rules ?? [],
            fn ($rule) => array_key_exists($rule, Tournament::TIEBREAKER_RULES),
        ));

        $rows = [];

        $groupsByWins = $applications->groupBy(fn (PlayerApplication $application) => $this->totals[$application->id]['wins'])
            ->sortKeysDesc();

        foreach ($groupsByWins as $group) {
            foreach ($this->resolve($group->all(), $rules) as $tiedGroup) {
                // Every member of a still-tied group shares the rank of its first slot.
                $rank = count($rows) + 1;

                foreach ($tiedGroup as $application) {
                    $rows[] = [
                        'application' => $application,
                        'name' => $this->entryName($application),
                        ...$this->totals[$application->id],
                        'rank' => $rank,
                        'tied' => count($tiedGroup) > 1,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * The entry that won the category outright, or null if the standings
     * aren't decided (no entries, or the top spot is still level).
     */
    public function champion(TournamentCategory $tournamentCategory): ?PlayerApplication
    {
        $top = $this->for($tournamentCategory)[0] ?? null;

        return $top && ! $top['tied'] ? $top['application'] : null;
    }

    private function tally(Matches $match): void
    {
        $playerSideMap = $match->playerSideMap();
        $diff = ['A' => 0, 'B' => 0];

        foreach ($match->games as $game) {
            $scores = $game->scores($playerSideMap);
            $diff['A'] += $scores['A'] - $scores['B'];
            $diff['B'] += $scores['B'] - $scores['A'];
        }

        foreach (['A', 'B'] as $side) {
            $application = $match->applicationForSide($side);

            if (! $application || ! isset($this->totals[$application->id])) {
                continue;
            }

            $won = $application->id === $match->winner_application_id;

            $this->totals[$application->id][$won ? 'wins' : 'losses']++;
            $this->totals[$application->id]['point_diff'] += $diff[$side];
        }
    }

    /**
     * Split entries level on wins into ordered groups, applying each rule in
     * turn to whatever is still tied. A group of more than one entry that
     * survives every rule is unresolved.
     *
     * @param  list<PlayerApplication>  $entries
     * @param  list<string>  $rules
     * @return list<list<PlayerApplication>>
     */
    private function resolve(array $entries, array $rules): array
    {
        if (count($entries) <= 1 || $rules === []) {
            return [$entries];
        }

        $rule = array_shift($rules);

        $scores = [];

        foreach ($entries as $entry) {
            $scores[$entry->id] = $this->tiebreakScore($rule, $entry, $entries);
        }

        $resolved = [];

        collect($entries)
            ->groupBy(fn (PlayerApplication $entry) => $scores[$entry->id])
            ->sortKeysDesc()
            ->each(function (Collection $subgroup) use (&$resolved, $rules): void {
                array_push($resolved, ...$this->resolve($subgroup->all(), $rules));
            });

        return $resolved;
    }

    /**
     * Higher is better. Head-to-head counts wins only in matches between the
     * tied entries, so a 3-way circle (A>B>C>A) leaves everyone level.
     *
     * @param  list<PlayerApplication>  $tiedEntries
     */
    private function tiebreakScore(string $rule, PlayerApplication $entry, array $tiedEntries): int
    {
        if ($rule === 'point_differential') {
            return $this->totals[$entry->id]['point_diff'];
        }

        $tiedIds = collect($tiedEntries)->pluck('id');

        return $this->completedMatches
            ->filter(fn (Matches $match) => $match->winner_application_id === $entry->id
                && $match->matchApplications->pluck('application_id')->diff([$entry->id])->every(fn ($id) => $tiedIds->contains($id)))
            ->count();
    }

    private function entryName(PlayerApplication $application): string
    {
        return $application->applicationPlayers
            ->map(fn ($applicationPlayer) => $applicationPlayer->player?->name)
            ->filter()
            ->implode(' / ');
    }
}
