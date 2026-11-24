<?php

use App\Models\User;

it('shows the loading indicator on the public pages', function (string $uri) {
    $this->get($uri)
        ->assertOk()
        ->assertSee('app-loading', false)
        ->assertSee('Livewire.interceptMessage', false);
})->with(['/', '/login', '/register']);

it('shows the loading indicator on the signed-in portals', function (string $role, string $uri) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get($uri)
        ->assertOk()
        ->assertSee('app-loading', false);
})->with([
    ['admin', '/admin/dashboard'],
    ['umpire', '/umpire/dashboard'],
    ['player', '/player/dashboard'],
]);

it('ignores background polls so refreshes stay silent', function () {
    $this->get('/')->assertSee("action.metadata?.type === 'poll'", false);
});

it('uses its own indicator for page transitions instead of the default blue bar', function () {
    expect(config('livewire.navigate.show_progress_bar'))->toBeFalse();
});
