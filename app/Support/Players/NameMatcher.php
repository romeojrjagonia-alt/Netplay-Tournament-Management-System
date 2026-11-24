<?php

namespace App\Support\Players;

use Illuminate\Support\Str;

/**
 * Whether two typed names are the same person's, ignoring case, accents,
 * punctuation and the middle initial (the first and last word must match).
 */
class NameMatcher
{
    public static function same(?string $a, ?string $b): bool
    {
        [$firstA, $lastA] = self::firstAndLast($a);
        [$firstB, $lastB] = self::firstAndLast($b);

        return $firstA !== '' && $firstA === $firstB && $lastA === $lastB;
    }

    /**
     * Whether the names share their first word or their last word.
     */
    public static function overlaps(?string $a, ?string $b): bool
    {
        [$firstA, $lastA] = self::firstAndLast($a);
        [$firstB, $lastB] = self::firstAndLast($b);

        return ($firstA !== '' && $firstA === $firstB) || ($lastA !== '' && $lastA === $lastB);
    }

    /**
     * A stable key for grouping players by name: "first|last".
     */
    public static function key(?string $name): string
    {
        return implode('|', self::firstAndLast($name));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function firstAndLast(?string $name): array
    {
        $clean = Str::of((string) $name)->ascii()->lower()->replaceMatches('/[^a-z\s-]/', ' ')->toString();
        $words = preg_split('/\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY);

        return [$words[0] ?? '', $words[count($words) - 1] ?? ''];
    }
}
