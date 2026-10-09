<?php

use App\Http\Controllers\Umpire\MatchScoresheetController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Public (no account needed): walk-in players register and pay for tournaments
// using the reference code issued with their application.
Volt::route('tournaments/{tournament}/register', 'tournaments.register')
    ->name('tournaments.register');

Volt::route('applications/{reference}/payment', 'applications.payment')
    ->name('applications.payment');

Route::get('dashboard', function () {
    return match (auth()->user()->role) {
        'admin' => redirect()->route('admin.dashboard'),
        'player' => redirect()->route('player.dashboard'),
        'umpire' => redirect()->route('umpire.dashboard'),
        default => view('dashboard'),
    };
})
    ->middleware('auth')
    ->name('dashboard');

Volt::route('admin/dashboard', 'admin.dashboard')
    ->middleware('auth')
    ->name('admin.dashboard');

Volt::route('admin/events', 'admin.events.index')
    ->middleware('auth')
    ->name('admin.events.index');

// Must be registered before admin/events/{tournament} — otherwise "create"
// is greedily matched as a tournament route-key by the dynamic route below.
Volt::route('admin/events/create', 'admin.events.wizard.details')
    ->middleware('auth')
    ->name('admin.events.create');

Volt::route('admin/events/{tournament}/details', 'admin.events.wizard.details')
    ->middleware('auth')
    ->name('admin.events.wizard.details');

Volt::route('admin/events/{tournament}/edit', 'admin.events.edit')
    ->middleware('auth')
    ->name('admin.events.edit');

Volt::route('admin/categories', 'admin.categories.index')
    ->middleware('auth')
    ->name('admin.categories.index');

Volt::route('admin/events/{tournament}/review', 'admin.events.wizard.review')
    ->middleware('auth')
    ->name('admin.events.wizard.review');

Volt::route('admin/events/{tournament}/schedule', 'admin.events.schedule.index')
    ->middleware('auth')
    ->name('admin.events.schedule.index');

Volt::route('admin/events/{tournament}/categories/{tournamentCategory}/schedule', 'admin.events.schedule.show')
    ->middleware('auth')
    ->name('admin.events.schedule.show');

Volt::route('admin/events/{tournament}', 'admin.events.show')
    ->middleware('auth')
    ->name('admin.events.show');

Volt::route('player/dashboard', 'player.dashboard')
    ->middleware('auth')
    ->name('player.dashboard');

Volt::route('player/events', 'player.events')
    ->middleware('auth')
    ->name('player.events');

Volt::route('player/matches', 'player.matches')
    ->middleware('auth')
    ->name('player.matches');

Volt::route('umpire/dashboard', 'umpire.dashboard')
    ->middleware('auth')
    ->name('umpire.dashboard');

Volt::route('umpire/matches', 'umpire.matches')
    ->middleware('auth')
    ->name('umpire.matches');

Volt::route('umpire/events', 'umpire.events.index')
    ->middleware('auth')
    ->name('umpire.events.index');

Volt::route('umpire/events/{tournament}', 'umpire.events.show')
    ->middleware('auth')
    ->name('umpire.events.show');

Volt::route('umpire/matches/{match}/scoring', 'umpire.matches.scoring')
    ->middleware('auth')
    ->name('umpire.matches.scoring');

Route::middleware('auth')->group(function () {
    Route::get('umpire/matches/{match}/scoresheet', [MatchScoresheetController::class, 'show'])
        ->name('umpire.matches.scoresheet');

    Route::get('umpire/matches/{match}/scoresheet/download', [MatchScoresheetController::class, 'download'])
        ->name('umpire.matches.scoresheet.download');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
