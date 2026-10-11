<?php

namespace App\Support\Bracket;

use App\Models\Matches;
use App\Models\PlayerApplication;
use App\Models\TournamentCategory;

/**
 * Turns a category's generated single-elimination matches into the plain
 * data structure brackets-viewer.js renders. Display only: the bracket's
 * rows stay the source of truth and nothing here is stored.
 */
class BracketsViewerData
{
    /** brackets-viewer match status codes. */
    private const STATUS_LOCKED = 0;

    private const STATUS_WAITING = 1;

    private const STATUS_READY = 2;

    private const STATUS_RUNNING = 3;

    private const STATUS_COMPLETED = 4;

    /**
     * @return array{stages: list<array<string, mixed>>, matches: list<array<string, mixed>>, matchGames: list<array<string, mixed>>, participants: list<array<string, mixed>>}
     */
    public function for(TournamentCategory $tournamentCategory): array
    {
        $matches = $tournamentCategory->matches()
            ->where('bracket_type', 'single_elimination')
            ->with(['games', 'matchApplications.application.applicationPlayers.player'])
            ->orderBy('round')
            ->orderBy('position')
            ->get();

        $participants = [];
        $viewerMatches = [];

        foreach ($matches as $match) {
            foreach ($match->matchApplications as $matchApplication) {
                $application = $matchApplication->application;

                if ($application && ! isset($participants[$application->id])) {
                    $participants[$application->id] = [
                        'id' => $application->id,
                        'tournament_id' => $tournamentCategory->tournament_id,
                        'name' => $this->name($application),
                    ];
                }
            }

            $viewerMatches[] = $this->match($match);
        }

        $firstRoundMatches = $matches->where('round', 1)->count();

        return [
            'stages' => [[
                'id' => 1,
                'tournament_id' => $tournamentCategory->tournament_id,
                'name' => $tournamentCategory->category?->name ?? 'Bracket',
                'type' => 'single_elimination',
                'number' => 1,
                'settings' => ['size' => $firstRoundMatches * 2, 'consolationFinal' => false],
            ]],
            'matches' => $viewerMatches,
            'matchGames' => [],
            'participants' => array_values($participants),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function match(Matches $match): array
    {
        $isFirstRound = $match->round === 1;
        $sides = ['A' => $match->applicationForSide('A'), 'B' => $match->applicationForSide('B')];

        $isBye = $isFirstRound && ($sides['A'] === null || $sides['B'] === null);
        $isDecided = $match->status === 'completed' && $match->winner_application_id !== null;

        return [
            'id' => $match->id,
            'stage_id' => 1,
            'group_id' => 1,
            'round_id' => $match->round,
            'number' => $match->position,
            'child_count' => 0,
            'status' => $this->status($match, $sides, $isBye),
            'opponent1' => $this->opponent($match, $sides['A'], $isFirstRound, $isDecided, $isBye),
            'opponent2' => $this->opponent($match, $sides['B'], $isFirstRound, $isDecided, $isBye),
        ];
    }

    /**
     * A null opponent is drawn as BYE and an opponent without an id as TBD
     * (still waiting on an earlier round).
     *
     * @return array<string, mixed>|null
     */
    private function opponent(Matches $match, ?PlayerApplication $application, bool $isFirstRound, bool $isDecided, bool $isBye): ?array
    {
        if ($application === null) {
            return $isFirstRound ? null : ['id' => null];
        }

        $opponent = ['id' => $application->id];

        if ($isBye) {
            $opponent['result'] = $isDecided ? 'win' : null;

            return array_filter($opponent, fn ($value) => $value !== null);
        }

        if ($match->games->isNotEmpty() || $isDecided) {
            $opponent['score'] = $match->games->where('winner_application_id', $application->id)->count();
        }

        if ($isDecided) {
            $opponent['result'] = $match->winner_application_id === $application->id ? 'win' : 'loss';
        }

        return $opponent;
    }

    /**
     * @param  array{A: PlayerApplication|null, B: PlayerApplication|null}  $sides
     */
    private function status(Matches $match, array $sides, bool $isBye): int
    {
        if ($match->status === 'completed') {
            return self::STATUS_COMPLETED;
        }

        if ($isBye) {
            return self::STATUS_LOCKED;
        }

        $filled = count(array_filter($sides));

        return match (true) {
            $filled === 0 => self::STATUS_LOCKED,
            $filled === 1 => self::STATUS_WAITING,
            $match->status === 'in_progress' || $match->games->isNotEmpty() => self::STATUS_RUNNING,
            default => self::STATUS_READY,
        };
    }

    private function name(PlayerApplication $application): string
    {
        return $application->applicationPlayers
            ->map(fn ($applicationPlayer) => $applicationPlayer->player?->name)
            ->filter()
            ->implode(' / ') ?: 'Entry #'.$application->id;
    }
}
