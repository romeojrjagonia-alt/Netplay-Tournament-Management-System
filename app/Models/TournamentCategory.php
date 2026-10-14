<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TournamentCategory extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tournament_id',
        'category_id',
        'registration_fee',
        'max_slots',
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
            'registration_fee' => 'decimal:2',
        ];
    }

    /**
     * The category's name, prefixed with its level when set, e.g. "Level G (Mixed Doubles)".
     */
    protected function categoryLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->category->level
            ? "{$this->category->level} ({$this->category->name})"
            : $this->category->name);
    }

    /**
     * Restrict to categories a player of the given gender and age is allowed
     * to register for. A "mixed" category gender always qualifies; any other
     * player gender (including null) only qualifies for
     * "mixed" categories. Null age bounds on the category are unrestricted.
     */
    public function scopeEligibleFor(Builder $query, ?string $gender, ?int $age): Builder
    {
        return $query->whereHas('category', function (Builder $category) use ($gender, $age): void {
            if ($gender === 'male' || $gender === 'female') {
                $category->whereIn('gender', [$gender, 'mixed']);
            } else {
                $category->where('gender', 'mixed');
            }

            if ($age !== null) {
                $category->where(fn (Builder $q) => $q->whereNull('age_min')->orWhere('age_min', '<=', $age))
                    ->where(fn (Builder $q) => $q->whereNull('age_max')->orWhere('age_max', '>=', $age));
            }
        });
    }

    /**
     * Whether this category's matches have already been generated.
     */
    public function hasGeneratedDraw(): bool
    {
        return $this->matches()->exists();
    }

    /**
     * Whether `$application` could be moved into this category.
     *
     * `team_size_mismatch` is a hard block; `ineligible_players` (players
     * whose gender or age falls outside this category) is only a warning an
     * admin may override.
     *
     * @return array{team_size_mismatch: bool, ineligible_players: Collection<int, Player>}
     */
    public function eligibilityIssuesFor(PlayerApplication $application): array
    {
        $application->loadMissing('applicationPlayers.player');
        $this->loadMissing('category');

        $players = $application->applicationPlayers->map->player->filter();
        $category = $this->category;

        $ineligiblePlayers = $players->reject(function (Player $player) use ($category): bool {
            $genderOk = $category->gender === 'mixed' || $player->gender === $category->gender;
            $ageOk = $player->age === null
                || (($category->age_min === null || $player->age >= $category->age_min)
                    && ($category->age_max === null || $player->age <= $category->age_max));

            return $genderOk && $ageOk;
        })->values();

        return [
            'team_size_mismatch' => $application->applicationPlayers->count() !== (int) $category->team_size,
            'ineligible_players' => $ineligiblePlayers,
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Entries holding a slot: every application that hasn't been rejected,
     * so a category can't be oversold while applications are still pending.
     */
    public function activeEntriesCount(): int
    {
        return $this->playerApplications()->where('status', '!=', 'rejected')->count();
    }

    public function isFull(): bool
    {
        return $this->activeEntriesCount() >= $this->max_slots;
    }

    /**
     * Lock the category row and refuse a new application when it is full.
     * Call inside the transaction that creates the application, so two
     * simultaneous registrations can't both take the last slot.
     *
     * @throws \RuntimeException when no slot is left
     */
    public static function lockAndAssertOpenSlot(int $tournamentCategoryId): void
    {
        $tournamentCategory = static::query()->whereKey($tournamentCategoryId)->lockForUpdate()->firstOrFail();

        if ($tournamentCategory->isFull()) {
            throw new \RuntimeException('This category is full — all '.$tournamentCategory->max_slots.' slots are taken.');
        }
    }

    public function playerApplications(): HasMany
    {
        return $this->hasMany(PlayerApplication::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class);
    }
}
