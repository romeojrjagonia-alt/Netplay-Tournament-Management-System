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
}
