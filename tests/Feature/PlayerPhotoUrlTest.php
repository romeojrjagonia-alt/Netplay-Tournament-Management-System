<?php

use App\Models\Player;
use Illuminate\Support\Facades\Storage;

test('a player with an uploaded photo resolves its url through the public disk', function () {
    Storage::fake('public');

    $player = Player::create([
        'name' => 'Photo Player',
        'photo_path' => 'player-photos/example.jpg',
    ]);

    expect($player->photo_url)->toBe(Storage::disk('public')->url('player-photos/example.jpg'));
});

test('a player without an uploaded photo has no photo url', function () {
    $player = Player::create(['name' => 'No Photo Player']);

    expect($player->photo_url)->toBeNull();
});
