<?php

use App\Models\Matches;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('can be run repeatedly without duplicating data or failing', function () {
    Storage::fake('public');

    // The first run has no players yet, so demo matches only appear on the second.
    $this->seed();
    $this->seed();

    $counts = [
        User::count(),
        Tournament::count(),
        Player::count(),
        PlayerApplication::count(),
        Matches::count(),
    ];

    $this->seed();

    expect([
        User::count(),
        Tournament::count(),
        Player::count(),
        PlayerApplication::count(),
        Matches::count(),
    ])->toBe($counts);

    expect(User::where('email', 'test@example.com')->count())->toBe(1);
});
