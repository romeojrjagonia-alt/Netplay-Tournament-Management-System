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
        'location',
        'scoring_system',
        'date_start',
        'date_end',
        'time',
        'specific_days',
        'registration_start',
        'registration_end',
        'status',
        'tiebreaker_rules',
    ];

    /**
     * Round-robin tiebreakers an admin can chain, keyed by the value stored
     * in `tiebreaker_rules`.
     *
     * @var array<string, string>
     */
    public const TIEBREAKER_RULES = [
        'head_to_head' => 'Head-to-Head',
        'point_differential' => 'Point Differential',
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
            'tiebreaker_rules' => 'array',
        ];
    }

    /**
     * The publicly accessible URL for the tournament's uploaded poster, if any.
     */
    protected function posterUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->poster_path ? asset('storage/'.$this->poster_path) : null);
    }

    /**
     * The date range as printed on score sheets, e.g. "December 5-7, 2025".
     */
    protected function dateRangeLabel(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->date_start->isSameDay($this->date_end)) {
                return $this->date_start->format('F j, Y');
            }

            if ($this->date_start->isSameMonth($this->date_end)) {
                return $this->date_start->format('F j').'-'.$this->date_end->format('j, Y');
            }

            return $this->date_start->format('F j, Y').' - '.$this->date_end->format('F j, Y');
        });
    }

    /**
     * Published tournaments currently within their registration window.
     */
    public function scopeAcceptingRegistrations(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereDate('registration_start', '<=', now())
            ->whereDate('registration_end', '>=', now());
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    protected function isPublished(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === 'published');
    }

    /**
     * A published tournament whose date range has already passed.
     */
    protected function isConcluded(): Attribute
    {
        return Attribute::get(fn (): bool => $this->isPublished && $this->date_end->isPast());
    }

    /**
     * The lifecycle label shown on the admin events list — Draft, Published,
     * or Concluded — distinct from whether registration is currently open.
     */
    protected function lifecycleLabel(): Attribute
    {
        return Attribute::get(function (): string {
            if (! $this->isPublished) {
                return 'Draft';
            }

            return $this->isConcluded ? 'Concluded' : 'Published';
        });
    }

    public function tournamentCategories(): HasMany
    {
        return $this->hasMany(TournamentCategory::class);
    }
}
