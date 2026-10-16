<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Player extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'birth_date',
        'age',
        'gender',
        'phone_number',
        'email',
        'address',
        'club',
        'photo_path',
        'level',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Player $player) {
            if ($player->birth_date) {
                $player->age = $player->birth_date->age;
            }
        });
    }

    /**
     * The publicly accessible URL for the player's uploaded photo, if any.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null);
    }

    /**
     * The player's initials, for avatar placeholders.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statistic(): HasOne
    {
        return $this->hasOne(PlayerStatistic::class);
    }

    public function linkRequests(): HasMany
    {
        return $this->hasMany(PlayerLinkRequest::class);
    }

    public function applicationPlayers(): HasMany
    {
        return $this->hasMany(ApplicationPlayer::class);
    }

    /**
     * The tournament applications this player is (or was) part of, singles
     * or as one half of a doubles entry. `application_players` is the pivot.
     */
    public function playerApplications(): BelongsToMany
    {
        return $this->belongsToMany(PlayerApplication::class, 'application_players', 'player_id', 'application_id')
            ->withPivot('level_at_apply')
            ->withTimestamps();
    }

    public function rallies(): HasMany
    {
        return $this->hasMany(Rally::class, 'scoring_player_id');
    }
}
