<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
