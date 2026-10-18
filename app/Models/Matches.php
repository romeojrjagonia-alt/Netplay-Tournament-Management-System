<?php

namespace App\Models;

use App\Observers\MatchObserver;
use App\Support\Badminton\GameRules;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([MatchObserver::class])]
class Matches extends Model
{
    /** Rally columns on the printed score sheet. */
    public const SCORESHEET_RALLIES = 31;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tournament_category_id',
        'bracket_type',
        'round',
        'position',
        'winner_application_id',
        'status',
        'scheduled_at',
        'schedule_notified_at',
        'court',
        'assigned_umpire_id',
        'team_a_ready_at',
        'team_b_ready_at',
        'result_type',
        'line_judge_1_name',
        'line_judge_2_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'schedule_notified_at' => 'datetime',
            'team_a_ready_at' => 'datetime',
            'team_b_ready_at' => 'datetime',
        ];
    }

    /**
     * Decided because a team did not show up, with no points played.
     */
    public function isWalkover(): bool
    {
        return $this->result_type === 'walkover';
    }

    public function tournamentCategory(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    public function winnerApplication(): BelongsTo
    {
        return $this->belongsTo(PlayerApplication::class, 'winner_application_id');
    }

    public function assignedUmpire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_umpire_id');
    }

    /**
     * Whether this match is scored as a 31-point single set (first to 31
     * wins, no 2-point lead) rather than best of 3 to 21.
     */
    public function usesSinglePointSystem(): bool
    {
        $this->loadMissing('tournamentCategory.tournament');

        return GameRules::isSingleSet($this->tournamentCategory?->tournament?->scoring_system);
    }

    public function matchApplications(): HasMany
    {
        return $this->hasMany(MatchApplication::class, 'match_id')->orderBy('slot')->orderBy('id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(MatchGame::class, 'match_id');
    }

    /**
     * Which of this match's two entries ('A' or 'B', in matchApplications
     * order) each player belongs to. Used to attribute a rally's scoring
     * player back to their team.
     *
     * @return array<int, 'A'|'B'>
     */
    public function playerSideMap(): array
    {
        // Includes .player so callers reading applicationPlayers->player
        // (e.g. team roster names) don't each trigger their own lazy query.
        $this->loadMissing('matchApplications.application.applicationPlayers.player');

        $map = [];

        foreach ($this->matchApplications as $index => $matchApplication) {
            $side = $this->sideOf($matchApplication, $index);

            foreach ($matchApplication->application?->applicationPlayers ?? [] as $applicationPlayer) {
                $map[$applicationPlayer->player_id] = $side;
            }
        }

        return $map;
    }

    /**
     * The players on the given side, e.g. "Mark Santos / Juan Torres".
     */
    public function sideLabel(string $side): string
    {
        $this->loadMissing('matchApplications.application.applicationPlayers.player');

        return $this->applicationForSide($side)
            ?->applicationPlayers
            ->map(fn ($applicationPlayer) => $applicationPlayer->player?->name)
            ->filter()
            ->implode(' / ') ?? '';
    }

    /**
     * The players on the given side, in entry order, one score sheet row each.
     *
     * @return list<array{id: int, name: string}>
     */
    public function sidePlayers(string $side): array
    {
        $this->loadMissing('matchApplications.application.applicationPlayers.player');

        return $this->applicationForSide($side)
            ?->applicationPlayers
            ->map(fn ($applicationPlayer) => ['id' => $applicationPlayer->player_id, 'name' => $applicationPlayer->player?->name ?? ''])
            ->values()
            ->all() ?? [];
    }

    /**
     * One entry per set to print on the score sheet, each its own sheet.
     * Like the paper form, each team's block is numbered by that TEAM'S running
     * score: `scorers['A'][n]` is the player who won Team 1's n-th point (and
     * `['B']` Team 2's), which is what the black cells show. The side that
     * reaches the target has its last cell filled, so the sheet reads as the
     * result. Numbering by overall rally would hide the end of any game longer
     * than 31 rallies (a 31-30 game is 61). The form has 31 point columns.
     * Always at least one set, so an unplayed match still prints a blank sheet.
     *
     * @return list<array{number: int, winner: 'A'|'B'|null, scorers: array{A: array<int, int>, B: array<int, int>}}>
     */
    public function scoresheetSets(): array
    {
        $this->loadMissing(['games.rallies', 'matchApplications.application.applicationPlayers']);

        $playerSideMap = $this->playerSideMap();
        $singleSet = $this->usesSinglePointSystem();
        $games = $this->games->sortBy('game_number')->values();

        if ($games->isEmpty()) {
            return [['number' => 1, 'winner' => null, 'scorers' => ['A' => [], 'B' => []]]];
        }

        return $games->map(function (MatchGame $game) use ($playerSideMap, $singleSet): array {
            $points = ['A' => 0, 'B' => 0];
            $scorers = ['A' => [], 'B' => []];

            foreach ($game->rallies->sortBy('rally_number') as $rally) {
                $side = $playerSideMap[$rally->scoring_player_id] ?? null;

                if ($side === null) {
                    continue;
                }

                $points[$side]++;

                if ($points[$side] <= self::SCORESHEET_RALLIES) {
                    $scorers[$side][$points[$side]] = $rally->scoring_player_id;
                }
            }

            return [
                'number' => $game->game_number,
                'winner' => $game->winningSide($playerSideMap, $singleSet),
                'scorers' => $scorers,
            ];
        })->all();
    }

    /**
     * Each game's point score and winning side, in play order. A decided
     * game takes its stored winner; one with no stored winner falls back to
     * what its rally log says, and stays null while still being played.
     *
     * @return list<array{number: int, A: int, B: int, winner: 'A'|'B'|null}>
     */
    public function gameScores(): array
    {
        $this->loadMissing(['games.rallies', 'matchApplications.application.applicationPlayers']);

        $playerSideMap = $this->playerSideMap();
        $applicationIdA = $this->applicationForSide('A')?->id;
        $applicationIdB = $this->applicationForSide('B')?->id;
        $singleSet = $this->usesSinglePointSystem();

        return $this->games
            ->sortBy('game_number')
            ->values()
            ->map(function (MatchGame $game) use ($playerSideMap, $applicationIdA, $applicationIdB, $singleSet): array {
                $scores = $game->scores($playerSideMap);

                $winner = match (true) {
                    $game->winner_application_id === null => $game->winningSide($playerSideMap, $singleSet),
                    $game->winner_application_id === $applicationIdA => 'A',
                    $game->winner_application_id === $applicationIdB => 'B',
                    default => null,
                };

                return ['number' => $game->game_number, 'A' => $scores['A'], 'B' => $scores['B'], 'winner' => $winner];
            })
            ->all();
    }

    /**
     * The application (entry) on the given side ('A' or 'B') of this match.
     */
    public function applicationForSide(string $side): ?PlayerApplication
    {
        $this->loadMissing('matchApplications.application');

        // Bracket matches pin each entry to a slot, so a half-filled match
        // keeps its lone entry on its own side.
        foreach ($this->matchApplications as $index => $matchApplication) {
            if ($this->sideOf($matchApplication, $index) === $side) {
                return $matchApplication->application;
            }
        }

        return null;
    }

    /**
     * The side ('A' or 'B') of the entry that won this match, or null while
     * it has no winner.
     *
     * @return 'A'|'B'|null
     */
    public function winnerSide(): ?string
    {
        if ($this->winner_application_id === null) {
            return null;
        }

        foreach (['A', 'B'] as $side) {
            if ($this->applicationForSide($side)?->id === $this->winner_application_id) {
                return $side;
            }
        }

        return null;
    }

    /**
     * @return 'A'|'B'
     */
    private function sideOf(MatchApplication $matchApplication, int $index): string
    {
        if ($matchApplication->slot !== null) {
            return $matchApplication->slot === 1 ? 'A' : 'B';
        }

        return $index === 0 ? 'A' : 'B';
    }
}
