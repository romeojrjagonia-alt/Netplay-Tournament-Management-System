<?php

namespace App\Actions\Players;

use App\Models\ApplicationPlayer;
use App\Models\Player;
use App\Models\Rally;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Attaches a guest player record to an account. If the account already has
 * its own (empty, sign-up created) player, that one is folded into the guest
 * record, which survives because it carries the history: its profile wins,
 * blanks are filled from sign-up, and the sign-up photo and email are kept.
 */
class MergePlayerRecords
{
    use CombinesPlayerRecords;

    /**
     * Profile fields a guest record fills in from the account's own player
     * only when the guest left them empty.
     *
     * @var list<string>
     */
    private const FILL_IF_BLANK = ['birth_date', 'gender', 'address', 'club', 'phone_number', 'level'];

    /**
     * @throws InvalidArgumentException when the records can't be combined
     */
    public function handle(Player $guest, User $user): Player
    {
        return DB::transaction(function () use ($guest, $user): Player {
            $guest = Player::query()->lockForUpdate()->findOrFail($guest->id);

            if ($guest->user_id !== null && $guest->user_id !== $user->id) {
                throw new InvalidArgumentException('That player record already belongs to another account.');
            }

            $own = $user->player()->lockForUpdate()->first();

            if ($own === null || $own->is($guest)) {
                $guest->update(['user_id' => $user->id] + ($guest->email ? [] : ['email' => $user->email]));

                return $guest;
            }

            $this->assertNoSharedCategory($guest, $own);

            ApplicationPlayer::where('player_id', $own->id)->update(['player_id' => $guest->id]);
            Rally::where('scoring_player_id', $own->id)->update(['scoring_player_id' => $guest->id]);
            $this->combineStatistics($guest, $own);

            $attributes = ['email' => $own->email ?: $user->email, 'photo_path' => $own->photo_path ?? $guest->photo_path];

            foreach (self::FILL_IF_BLANK as $field) {
                if ($guest->{$field} === null || $guest->{$field} === '') {
                    $attributes[$field] = $own->{$field};
                }
            }

            // Free the user_id first, then move the account onto the survivor.
            $own->delete();
            $guest->forceFill($attributes + ['user_id' => $user->id])->save();

            return $guest;
        });
    }
}
