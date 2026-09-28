<?php

namespace App\Support\Badminton;

/**
 * Standard BWF badminton scoring: a game is won at 21 points with a
 * 2-point lead, or outright at a 30-point hard cap (sudden death from
 * 29-29). A match is decided by whichever side wins 2 of its 3 games.
 */
final class GameRules
{
    public const POINTS_TO_WIN = 21;

    public const POINT_CAP = 30;

    /**
     * The side ('A' or 'B') that has won the game at this score, or null
     * if the game is still in progress.
     *
     * @return 'A'|'B'|null
     */
    public static function gameWinner(int $scoreA, int $scoreB): ?string
    {
        if ($scoreA >= self::POINT_CAP || $scoreB >= self::POINT_CAP) {
            return $scoreA > $scoreB ? 'A' : 'B';
        }

        if (($scoreA >= self::POINTS_TO_WIN || $scoreB >= self::POINTS_TO_WIN) && abs($scoreA - $scoreB) >= 2) {
            return $scoreA > $scoreB ? 'A' : 'B';
        }

        return null;
    }

    /**
     * The side that has won the match given the completed games' winners
     * so far (best of 3), or null if neither side has won 2 yet.
     *
     * @param  array<int, 'A'|'B'>  $gameWinners
     * @return 'A'|'B'|null
     */
    public static function matchWinner(array $gameWinners): ?string
    {
        $counts = array_count_values($gameWinners);

        foreach (['A', 'B'] as $side) {
            if (($counts[$side] ?? 0) >= 2) {
                return $side;
            }
        }

        return null;
    }
}
