<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'poster_path',
        'format',
        'sport',
        'scoring_system',
        'date_start',
        'date_end',
        'time',
        'specific_days',
        'registration_start',
        'registration_end',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'registration_start' => 'date',
            'registration_end' => 'date',
        ];
    }

    /**
     * The publicly accessible URL for the tournament's uploaded poster, if any.
     */
    protected function posterUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->poster_path ? asset('storage/'.$this->poster_path) : null);
    }

    public function scopeAcceptingRegistrations(Builder $query): void
    {
        $query->where('status', 'registration_open');
    }

    public function tournamentCategories(): HasMany
    {
        return $this->hasMany(TournamentCategory::class);
    }
}
