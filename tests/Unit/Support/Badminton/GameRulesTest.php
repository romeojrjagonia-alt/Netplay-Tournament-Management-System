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

test('a 31-point single set is won by the first side to reach 31 with no 2-point lead', function () {
    expect(GameRules::gameWinner(30, 29, singleSet: true))->toBeNull();
    expect(GameRules::gameWinner(31, 30, singleSet: true))->toBe('A');
    expect(GameRules::gameWinner(29, 31, singleSet: true))->toBe('B');
    expect(GameRules::gameWinner(21, 15, singleSet: true))->toBeNull();
});

test('a 31-point single set match is decided by its lone game', function () {
    expect(GameRules::matchWinner([], singleSet: true))->toBeNull();
    expect(GameRules::matchWinner(['B'], singleSet: true))->toBe('B');
    expect(GameRules::maxGames(singleSet: true))->toBe(1);
    expect(GameRules::maxGames())->toBe(3);
});

test('only the 31-point single set value is treated as single set', function () {
    expect(GameRules::isSingleSet(GameRules::SYSTEM_SINGLE_SET))->toBeTrue();
    expect(GameRules::isSingleSet('Best of 3 to 21'))->toBeFalse();
    expect(GameRules::isSingleSet('rally'))->toBeFalse();
    expect(GameRules::isSingleSet(null))->toBeFalse();
});
