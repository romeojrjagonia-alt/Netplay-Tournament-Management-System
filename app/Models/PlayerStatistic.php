<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStatistic extends Model
{
    /**
     * The primary key is player_id, not an auto-incrementing id (1:1 with players).
     *
     * @var string
     */
    protected $primaryKey = 'player_id';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'player_id',
        'matches_played',
        'wins',
        'losses',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
