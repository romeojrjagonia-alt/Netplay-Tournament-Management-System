<?php

namespace App\Actions\Players;

use App\Models\ApplicationPlayer;
use App\Models\Player;
use App\Models\PlayerLinkRequest;
use App\Models\Rally;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Folds two records of the same person into one so their history stays in a
 * single place (NETPLAY.md 4.3). The survivor is the record with an account,
 * otherwise the one with more entries, otherwise the older one; blanks on it
 * are filled from the other record, which is then deleted.
 */
class MergeDuplicatePlayers
{
    use CombinesPlayerRecords;

    /**
     * Fields the survivor takes from the other record only when blank.
     *
     * @var list<string>
     */
    private const FILL_IF_BLANK = ['birth_date', 'gender', 'address', 'club', 'phone_number', 'email', 'photo_path', 'level'];

    /**
     * @throws InvalidArgumentException when the records can't be combined
     */
    public function handle(Player $first, Player $second): Player
    {
        return DB::transaction(function () use ($first, $second): Player {
            $first = Player::query()->lockForUpdate()->findOrFail($first->id);
            $second = Player::query()->lockForUpdate()->findOrFail($second->id);

            if ($first->is($second)) {
                throw new InvalidArgumentException('Pick two different player records.');
            }

            if ($first->user_id !== null && $second->user_id !== null) {
                throw new InvalidArgumentException('Both records belong to accounts, so they are different people.');
            }

            [$survivor, $duplicate] = $this->survivorFirst($first, $second);

            $this->assertNoSharedCategory($survivor, $duplicate);

            ApplicationPlayer::where('player_id', $duplicate->id)->update(['player_id' => $survivor->id]);
            Rally::where('scoring_player_id', $duplicate->id)->update(['scoring_player_id' => $survivor->id]);
            $this->moveLinkRequests($survivor, $duplicate);
            $this->combineStatistics($survivor, $duplicate);

            $fill = [];

            foreach (self::FILL_IF_BLANK as $field) {
                if (($survivor->{$field} === null || $survivor->{$field} === '') && filled($duplicate->{$field})) {
                    $fill[$field] = $duplicate->{$field};
                }
            }

            $duplicate->delete();
            $survivor->forceFill($fill)->save();

            return $survivor;
        });
    }

    /**
     * @return array{0: Player, 1: Player} [survivor, duplicate]
     */
    private function survivorFirst(Player $first, Player $second): array
    {
        $rank = fn (Player $player): array => [
            $player->user_id !== null ? 1 : 0,
            ApplicationPlayer::where('player_id', $player->id)->count(),
            -$player->id,
        ];

        return $rank($first) >= $rank($second) ? [$first, $second] : [$second, $first];
    }

    /**
     * Requests are unique per account and record, so a request the survivor
     * already has for the same account is kept and the duplicate's is dropped.
     */
    private function moveLinkRequests(Player $survivor, Player $duplicate): void
    {
        foreach (PlayerLinkRequest::where('player_id', $duplicate->id)->get() as $request) {
            if (PlayerLinkRequest::where(['user_id' => $request->user_id, 'player_id' => $survivor->id])->exists()) {
                $request->delete();

                continue;
            }

            $request->update(['player_id' => $survivor->id]);
        }
    }
}
