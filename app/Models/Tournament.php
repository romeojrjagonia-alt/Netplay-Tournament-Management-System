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
