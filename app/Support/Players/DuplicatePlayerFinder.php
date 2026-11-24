<?php

namespace App\Support\Players;

use App\Models\DismissedPlayerPair;
use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * Suggests pairs of player records that look like the same person, for an
 * admin to merge. They are guesses: pairs an admin already dismissed, and
 * pairs where both records belong to accounts, are never suggested.
 */
class DuplicatePlayerFinder
{
    /**
     * @return Collection<int, array{a: Player, b: Player, reasons: list<string>}>
     */
    public function pairs(int $limit = 50): Collection
    {
        $players = Player::query()
            ->withCount('applicationPlayers')
            ->orderBy('id')
            ->get();

        $dismissed = DismissedPlayerPair::query()->get(['player_a_id', 'player_b_id'])
            ->mapWithKeys(fn ($pair) => [$pair->player_a_id.'-'.$pair->player_b_id => true]);

        /** @var array<string, list<string>> $reasonsByPair */
        $reasonsByPair = [];

        $note = function (Player $x, Player $y, string $reason) use (&$reasonsByPair, $dismissed): void {
            [$low, $high] = $x->id < $y->id ? [$x, $y] : [$y, $x];
            $key = $low->id.'-'.$high->id;

            if (isset($dismissed[$key]) || ($low->user_id !== null && $high->user_id !== null)) {
                return;
            }

            $reasonsByPair[$key] ??= [];

            if (! in_array($reason, $reasonsByPair[$key], true)) {
                $reasonsByPair[$key][] = $reason;
            }
        };

        $this->eachPairIn($players, fn (Player $p) => $p->email ? mb_strtolower($p->email) : null, 'Same email', null, $note);
        $this->eachPairIn(
            $players,
            fn (Player $p) => $p->birth_date ? NameMatcher::key($p->name).'|'.$p->birth_date->toDateString() : null,
            'Same name and birthdate',
            null,
            $note,
        );
        $this->eachPairIn(
            $players,
            fn (Player $p) => $p->phone_number ?: null,
            'Same phone and a matching name',
            fn (Player $x, Player $y) => NameMatcher::overlaps($x->name, $y->name),
            $note,
        );

        $byId = $players->keyBy('id');

        return collect($reasonsByPair)
            ->map(function (array $reasons, string $key) use ($byId): array {
                [$a, $b] = array_map('intval', explode('-', $key));

                return ['a' => $byId[$a], 'b' => $byId[$b], 'reasons' => $reasons];
            })
            ->take($limit)
            ->values();
    }

    /**
     * Players without a value for the signal are left out of the grouping.
     *
     * @param  Collection<int, Player>  $players
     * @param  callable(Player): ?string  $groupKey
     * @param  (callable(Player, Player): bool)|null  $accept
     * @param  callable(Player, Player, string): void  $note
     */
    private function eachPairIn(Collection $players, callable $groupKey, string $reason, ?callable $accept, callable $note): void
    {
        $groups = $players
            ->map(fn (Player $p) => ['key' => $groupKey($p), 'player' => $p])
            ->filter(fn (array $row) => $row['key'] !== null)
            ->groupBy('key');

        foreach ($groups as $rows) {
            $group = $rows->pluck('player')->values();

            for ($i = 0; $i < $group->count(); $i++) {
                for ($j = $i + 1; $j < $group->count(); $j++) {
                    if ($accept === null || $accept($group[$i], $group[$j])) {
                        $note($group[$i], $group[$j], $reason);
                    }
                }
            }
        }
    }
}
