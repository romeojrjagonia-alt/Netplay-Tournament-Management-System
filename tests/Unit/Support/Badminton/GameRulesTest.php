<?php

use App\Support\Badminton\GameRules;

test('a game is not won before either side reaches 21', function () {
    expect(GameRules::gameWinner(19, 17))->toBeNull();
});

test('a game is won at 21 with a 2 point lead', function () {
    expect(GameRules::gameWinner(21, 19))->toBe('A');
    expect(GameRules::gameWinner(19, 21))->toBe('B');
});

test('a game is not yet won at 21 without a 2 point lead', function () {
    expect(GameRules::gameWinner(21, 20))->toBeNull();
});

test('a game is won outright at the 30 point cap regardless of lead', function () {
    expect(GameRules::gameWinner(30, 29))->toBe('A');
    expect(GameRules::gameWinner(29, 30))->toBe('B');
});

test('a match is not decided until one side has won 2 games', function () {
    expect(GameRules::matchWinner(['A']))->toBeNull();
    expect(GameRules::matchWinner(['A', 'B']))->toBeNull();
});

test('a match is won by whichever side wins 2 games', function () {
    expect(GameRules::matchWinner(['A', 'B', 'A']))->toBe('A');
    expect(GameRules::matchWinner(['A', 'A']))->toBe('A');
    expect(GameRules::matchWinner(['B', 'A', 'B']))->toBe('B');
});
