<?php

namespace App\Models;

use App\Support\Badminton\GameRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchGame extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'match_id',
        'game_number',
        'winner_application_id',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function winnerApplication(): BelongsTo
    {
        return $this->belongsTo(PlayerApplication::class, 'winner_application_id');
    }

    public function rallies(): HasMany
    {
        return $this->hasMany(Rally::class);
    }

    /**
     * This game's current point score for each side, derived from its
     * rally log.
     *
     * @param  array<int, 'A'|'B'>  $playerSideMap  player_id => side, from Matches::playerSideMap()
     * @return array{A: int, B: int}
     */
    public function scores(array $playerSideMap): array
    {
        $scores = ['A' => 0, 'B' => 0];

        foreach ($this->rallies as $rally) {
            $side = $playerSideMap[$rally->scoring_player_id] ?? null;

            if ($side !== null) {
                $scores[$side]++;
            }
        }

        return $scores;
    }

    /**
     * The side that has won this game at its current score, or null if
     * it's still in progress.
     *
     * @param  array<int, 'A'|'B'>  $playerSideMap
     * @return 'A'|'B'|null
     */
    public function winningSide(array $playerSideMap): ?string
    {
        $scores = $this->scores($playerSideMap);

        return GameRules::gameWinner($scores['A'], $scores['B']);
    }
}
