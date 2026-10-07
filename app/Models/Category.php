<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'level',
        'gender',
        'age_min',
        'age_max',
        'team_size',
    ];

    /**
     * The "Gender" choices on the Create Categories form, each baking in
     * the gender and team size (1 or 2) the event type implies.
     *
     * @var array<string, array{name: string, gender: string, team_size: int}>
     */
    public const GENDER_TYPES = [
        'mens_singles' => ['name' => "Men's Singles", 'gender' => 'male', 'team_size' => 1],
        'womens_singles' => ['name' => "Women's Singles", 'gender' => 'female', 'team_size' => 1],
        'mens_doubles' => ['name' => "Men's Doubles", 'gender' => 'male', 'team_size' => 2],
        'womens_doubles' => ['name' => "Women's Doubles", 'gender' => 'female', 'team_size' => 2],
        'mixed_doubles' => ['name' => 'Mixed Doubles', 'gender' => 'mixed', 'team_size' => 2],
    ];

    /**
     * The selectable player levels.
     *
     * @var list<string>
     */
    public const LEVELS = [
        'Beginner',
        'Intermediate',
        'Level G',
        'Level F',
        'Level E',
        'Level D',
        'Level C',
        'Level B',
        'Level A',
        'Supermixed',
    ];

    /**
     * The age bracket as shown to admins. No bounds means open to all ages.
     */
    protected function ageLabel(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->age_min === null && $this->age_max === null => 'Open (all ages)',
            $this->age_max === null => "{$this->age_min} and above",
            $this->age_min === null => "Up to {$this->age_max}",
            default => "{$this->age_min} - {$this->age_max}",
        });
    }

    /**
     * The GENDER_TYPES key matching this category's gender and team size,
     * or null for a legacy category that fits none of them.
     */
    protected function genderTypeKey(): Attribute
    {
        return Attribute::get(function (): ?string {
            foreach (self::GENDER_TYPES as $key => $type) {
                if ($type['gender'] === $this->gender && $type['team_size'] === (int) $this->team_size) {
                    return $key;
                }
            }

            return null;
        });
    }

    /**
     * Restrict to categories identical to the given attributes — treating a
     * null age bound as "no bound" rather than a wildcard.
     *
     * @param  array{name: string, gender: string, team_size: int, level: ?string, age_min: ?int, age_max: ?int}  $attributes
     */
    public function scopeMatching(Builder $query, array $attributes): void
    {
        foreach (['name', 'gender', 'team_size', 'level', 'age_min', 'age_max'] as $column) {
            $attributes[$column] === null
                ? $query->whereNull($column)
                : $query->where($column, $attributes[$column]);
        }
    }

    /**
     * Whether a published event uses this category — it is then frozen so
     * live registrations and brackets stay consistent.
     */
    public function isLocked(): bool
    {
        return $this->tournamentCategories()
            ->whereHas('tournament', fn (Builder $tournament) => $tournament->published())
            ->exists();
    }

    public function tournamentCategories(): HasMany
    {
        return $this->hasMany(TournamentCategory::class);
    }
}
