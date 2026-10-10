<?php

namespace App\Models;

use App\Observers\MatchObserver;
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
        ];
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
     * `scorers` maps rally number => the player who won that rally, which is
     * what the black cells show. The form only has 31 rally columns, so a set
     * that runs longer prints its first 31 rallies. Always at least one set,
     * so an unplayed match still prints a blank sheet.
     *
     * @return list<array{number: int, winner: 'A'|'B'|null, scorers: array<int, int>}>
     */
    public function scoresheetSets(): array
    {
        $this->loadMissing(['games.rallies', 'matchApplications.application.applicationPlayers']);

        $playerSideMap = $this->playerSideMap();
        $games = $this->games->sortBy('game_number')->values();

        if ($games->isEmpty()) {
            return [['number' => 1, 'winner' => null, 'scorers' => []]];
        }

        return $games->map(function (MatchGame $game): array {
            $scorers = $game->rallies
                ->where('rally_number', '<=', self::SCORESHEET_RALLIES)
                ->pluck('scoring_player_id', 'rally_number')
                ->all();

            return [
                'number' => $game->game_number,
                'winner' => $game->winningSide($this->playerSideMap()),
                'scorers' => $scorers,
            ];
        })->all();
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
