<?php

namespace App\Models;

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

    public function tournamentCategories(): HasMany
    {
        return $this->hasMany(TournamentCategory::class);
    }
}
