<?php

use App\Support\Badminton\GameRules;

test('a pickleball game is won at 11 with a two point lead', function (int $a, int $b, ?string $winner) {
    expect(GameRules::gameWinner($a, $b, false, true))->toBe($winner);
})->with([
    'in progress' => [10, 8, null],
    'won 11-9' => [11, 9, 'A'],
    'not yet two clear' => [11, 10, null],
    'extended play' => [13, 11, 'A'],
    'no cap at 30' => [30, 29, null],
    'side b wins' => [7, 11, 'B'],
]);

test('only the pickleball scoring system is recognised as pickleball', function () {
    expect(GameRules::isPickleball(GameRules::SYSTEM_PICKLEBALL))->toBeTrue()
        ->and(GameRules::isPickleball(GameRules::SYSTEM_BEST_OF_THREE))->toBeFalse()
        ->and(GameRules::isPickleball(null))->toBeFalse();
});
