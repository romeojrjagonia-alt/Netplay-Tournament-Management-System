<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayerApplication extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tournament_category_id',
        'status',
    ];

    /**
     * Human-readable status, matching the wording already used on the
     * player dashboard's registration progress list.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->status) {
            'pending_verification' => 'Waiting for organizers verification',
            'payment_pending' => 'Verification confirmed, proceed to payment',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        });
    }

    public function tournamentCategory(): BelongsTo
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    public function applicationPlayers(): HasMany
    {
        return $this->hasMany(ApplicationPlayer::class, 'application_id');
    }

    public function matchApplications(): HasMany
    {
        return $this->hasMany(MatchApplication::class, 'application_id');
    }

    public function paymentDetails(): HasMany
    {
        return $this->hasMany(PaymentDetail::class, 'application_id');
    }

    public function wonMatches(): HasMany
    {
        return $this->hasMany(Matches::class, 'winner_application_id');
    }

    public function wonGames(): HasMany
    {
        return $this->hasMany(MatchGame::class, 'winner_application_id');
    }
}
