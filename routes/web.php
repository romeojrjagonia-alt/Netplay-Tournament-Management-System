<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', function () {
    return match (auth()->user()->role) {
        'admin' => redirect()->route('admin.dashboard'),
        'player' => redirect()->route('player.dashboard'),
        default => view('dashboard'),
    };
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Volt::route('admin/dashboard', 'admin.dashboard')
    ->middleware(['auth', 'verified'])
    ->name('admin.dashboard');

Volt::route('admin/events', 'admin.events.index')
    ->middleware(['auth', 'verified'])
    ->name('admin.events.index');

// Must be registered before admin/events/{tournament} — otherwise "create"
// is greedily matched as a tournament route-key by the dynamic route below.
Volt::route('admin/events/create', 'admin.events.wizard.details')
    ->middleware(['auth', 'verified'])
    ->name('admin.events.create');

Volt::route('admin/events/{tournament}/details', 'admin.events.wizard.details')
    ->middleware(['auth', 'verified'])
    ->name('admin.events.wizard.details');

Volt::route('admin/events/{tournament}/categories', 'admin.events.wizard.categories')
    ->middleware(['auth', 'verified'])
    ->name('admin.events.wizard.categories');

Volt::route('admin/events/{tournament}/review', 'admin.events.wizard.review')
    ->middleware(['auth', 'verified'])
    ->name('admin.events.wizard.review');

Volt::route('admin/events/{tournament}', 'admin.events.show')
    ->middleware(['auth', 'verified'])
    ->name('admin.events.show');

Volt::route('player/dashboard', 'player.dashboard')
    ->middleware(['auth', 'verified'])
    ->name('player.dashboard');

Volt::route('player/events', 'player.events')
    ->middleware(['auth', 'verified'])
    ->name('player.events');

Volt::route('player/matches', 'player.matches')
    ->middleware(['auth', 'verified'])
    ->name('player.matches');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
