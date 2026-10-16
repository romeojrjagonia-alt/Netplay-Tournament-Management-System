<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DismissedPlayerPair extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'player_a_id',
        'player_b_id',
    ];

    /**
     * Remember that two players are different people, whichever order given.
     */
    public static function dismiss(int $firstPlayerId, int $secondPlayerId): void
    {
        [$a, $b] = [min($firstPlayerId, $secondPlayerId), max($firstPlayerId, $secondPlayerId)];

        static::firstOrCreate(['player_a_id' => $a, 'player_b_id' => $b]);
    }
}
