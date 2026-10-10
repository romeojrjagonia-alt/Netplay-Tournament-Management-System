<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a normal signed in request keeps working and records the password hash', function () {
    $user = User::factory()->create(['role' => 'player']);

    $this->actingAs($user)
        ->get(route('settings.profile'))
        ->assertOk()
        ->assertSessionHas('password_hash_web');
});

test('a session is signed out after the password changes elsewhere', function () {
    $user = User::factory()->create(['role' => 'player']);

    $this->actingAs($user)
        ->get(route('settings.profile'))
        ->assertOk();

    $staleHash = session('password_hash_web');

    $user->update(['password' => Hash::make('a-new-password')]);

    $this->withSession(['password_hash_web' => $staleHash])
        ->actingAs($user->fresh())
        ->get(route('settings.profile'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('google only users without a password are not signed out', function () {
    $user = User::factory()->create(['role' => 'player', 'password' => null, 'google_id' => 'google-123']);

    $this->actingAs($user)
        ->get(route('settings.profile'))
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});
