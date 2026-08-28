<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rally extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'match_game_id',
        'scoring_player_id',
        'rally_number',
    ];

    public function matchGame(): BelongsTo
    {
        return $this->belongsTo(MatchGame::class);
    }

    public function scoringPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'scoring_player_id');
    }
}
