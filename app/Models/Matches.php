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
        return $this->hasMany(MatchApplication::class, 'match_id');
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
            $side = $index === 0 ? 'A' : 'B';

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
     * The sets to lay out on the score sheet: each side's final point total
     * and the side that won it. Always at least $minimumSets entries, so the
     * printed form keeps blank sets to fill in by hand.
     *
     * @return list<array{number: int, scores: array{A: int, B: int}, winner: 'A'|'B'|null}>
     */
    public function scoresheetSets(int $minimumSets = 2): array
    {
        $this->loadMissing(['games.rallies', 'matchApplications.application.applicationPlayers']);

        $playerSideMap = $this->playerSideMap();
        $games = $this->games->sortBy('game_number')->values();
        $sets = [];

        foreach (range(1, max($minimumSets, $games->count())) as $number) {
            $game = $games->firstWhere('game_number', $number);

            $sets[] = [
                'number' => $number,
                'scores' => $game?->scores($playerSideMap) ?? ['A' => 0, 'B' => 0],
                'winner' => $game?->winningSide($playerSideMap),
            ];
        }

        return $sets;
    }

    /**
     * The side that won the match: the recorded winner once it's confirmed,
     * otherwise whichever side has already taken 2 sets.
     *
     * @return 'A'|'B'|null
     */
    public function winnerSide(): ?string
    {
        $this->loadMissing(['games.rallies', 'matchApplications.application.applicationPlayers']);

        if ($this->winner_application_id) {
            foreach (['A', 'B'] as $side) {
                if ($this->applicationForSide($side)?->id === $this->winner_application_id) {
                    return $side;
                }
            }
        }

        $playerSideMap = $this->playerSideMap();

        return GameRules::matchWinner(
            $this->games->map(fn (MatchGame $game) => $game->winningSide($playerSideMap))->filter()->values()->all()
        );
    }

    /**
     * The application (entry) on the given side ('A' or 'B') of this match.
     */
    public function applicationForSide(string $side): ?PlayerApplication
    {
        $index = $side === 'A' ? 0 : 1;

        return $this->matchApplications->get($index)?->application;
    }
}
