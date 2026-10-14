<?php

namespace App\Support\Badminton;

/**
 * Two scoring systems are supported:
 *
 * - Best of 3 to 21 (the default): standard BWF scoring — a game is won at 21
 *   points with a 2-point lead, or outright at a 30-point hard cap (sudden
 *   death from 29-29). A match is decided by whichever side wins 2 of 3 games.
 * - 31-point single set: the match is one game, and the first side to reach
 *   31 points wins it, with no 2-point lead required.
 *
 * Which one applies is a tournament-level setting (`scoring_system`).
 */
final class GameRules
{
    public const POINTS_TO_WIN = 21;

    public const POINT_CAP = 30;

    public const SINGLE_SET_POINTS_TO_WIN = 31;

    /** The `tournaments.scoring_system` value for best of 3 to 21. */
    public const SYSTEM_BEST_OF_THREE = 'Best of 3 to 21';

    /** The `tournaments.scoring_system` value for the 31-point single set. */
    public const SYSTEM_SINGLE_SET = '31-Point Single Set';

    /**
     * Whether the given `tournaments.scoring_system` value is the 31-point
     * single set. Anything else scores as best of 3 to 21.
     */
    public static function isSingleSet(?string $scoringSystem): bool
    {
        return $scoringSystem === self::SYSTEM_SINGLE_SET;
    }

    /**
     * The most games a match can have under the given system.
     */
    public static function maxGames(bool $singleSet = false): int
    {
        return $singleSet ? 1 : 3;
    }

    /**
     * The point total a side must reach to win a game outright.
     */
    public static function pointsToWin(bool $singleSet = false): int
    {
        return $singleSet ? self::SINGLE_SET_POINTS_TO_WIN : self::POINTS_TO_WIN;
    }

    /**
     * The side ('A' or 'B') that has won the game at this score, or null
     * if the game is still in progress.
     *
     * @return 'A'|'B'|null
     */
    public static function gameWinner(int $scoreA, int $scoreB, bool $singleSet = false): ?string
    {
        if ($singleSet) {
            if ($scoreA >= self::SINGLE_SET_POINTS_TO_WIN || $scoreB >= self::SINGLE_SET_POINTS_TO_WIN) {
                return $scoreA > $scoreB ? 'A' : 'B';
            }

            return null;
        }

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
     * so far: first to win 2 games (best of 3), or the lone game in the
     * single set. Null if neither side has won enough yet.
     *
     * @param  array<int, 'A'|'B'>  $gameWinners
     * @return 'A'|'B'|null
     */
    public static function matchWinner(array $gameWinners, bool $singleSet = false): ?string
    {
        $needed = $singleSet ? 1 : 2;
        $counts = array_count_values($gameWinners);

        foreach (['A', 'B'] as $side) {
            if (($counts[$side] ?? 0) >= $needed) {
                return $side;
            }
        }

        return null;
    }
}
