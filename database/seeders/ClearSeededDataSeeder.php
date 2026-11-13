<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Category;
use App\Models\DismissedPlayerPair;
use App\Models\Matches;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\PlayerLinkRequest;
use App\Models\PlayerStatistic;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Empties the system back to a clean slate and keeps only the seeded admin
 * account (Admin@Netplay.com, see AdminSeeder), so the app can be demonstrated
 * or handed over from scratch. It is never called from DatabaseSeeder:
 *
 *   php artisan db:seed --class=ClearSeededDataSeeder
 *
 * This DELETES EVERYTHING else: every tournament with its entries, matches,
 * scores and payments; every player (walk-ins included) and their statistics;
 * every account other than the admin, with its sessions; link requests; and
 * the uploaded files (ID photos, payment proofs, pictures, posters) that those
 * records pointed to. Category templates are reference data and are kept, so
 * new events can still be created straight away.
 *
 * Safety:
 * - It asks for confirmation first. Where there is no one to ask (and always
 *   on production), set CLEAR_DATA_CONFIRMED=true to confirm in advance.
 * - It refuses to run if the seeded admin account does not exist, so nobody
 *   is ever locked out of an emptied system.
 * - The admin's own password, picture and player record (if any) are untouched.
 */
class ClearSeededDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(AdminSeeder::EMAIL)])->first();

        if (! $admin) {
            $this->command?->error('The seeded admin account ('.AdminSeeder::EMAIL.') does not exist, so nothing was deleted. Run AdminSeeder first.');

            return;
        }

        if (! $this->confirmed()) {
            return;
        }

        $summary = $this->summary($admin);
        $files = $this->uploadedFiles($admin);

        DB::transaction(function () use ($admin): void {
            // Cascades through categories offered, applications, entries,
            // matches, games, rallies and payments.
            Tournament::query()->delete();

            PlayerLinkRequest::query()->delete();
            DismissedPlayerPair::query()->delete();

            $adminPlayerIds = Player::query()->where('user_id', $admin->id)->pluck('id');

            // The admin's own player record (if they have one) stays, with a clean history.
            PlayerStatistic::query()->whereIn('player_id', $adminPlayerIds)->delete();
            Player::query()->whereNotIn('id', $adminPlayerIds)->delete();

            User::query()->where('id', '!=', $admin->id)->delete();

            DB::table('sessions')->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $admin->id))->delete();
            DB::table('password_reset_tokens')->where('email', '!=', $admin->email)->delete();
            DB::table('jobs')->delete();
            DB::table('failed_jobs')->delete();
        });

        Storage::disk('public')->delete($files);

        $this->report($admin, $summary, count($files));
    }

    /**
     * Where the person running it can't be asked, the CLEAR_DATA_CONFIRMED
     * flag stands in for the answer. Production never proceeds without it.
     */
    private function confirmed(): bool
    {
        if (filter_var(env('CLEAR_DATA_CONFIRMED', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        if (app()->environment('production')) {
            $this->command?->error('This would erase all data on a production system. Set CLEAR_DATA_CONFIRMED=true if that is really what you want. Nothing was deleted.');

            return false;
        }

        if ($this->command === null) {
            return true;
        }

        if (! $this->command->confirm('This permanently deletes every tournament, player and account except '.AdminSeeder::EMAIL.'. Continue?', false)) {
            $this->command->warn('Cancelled. Nothing was deleted.');

            return false;
        }

        return true;
    }

    /**
     * @return array<string, int>
     */
    private function summary(User $admin): array
    {
        return [
            'Tournaments' => Tournament::count(),
            'Applications' => PlayerApplication::count(),
            'Matches' => Matches::count(),
            'Players' => Player::where('user_id', '!=', $admin->id)->orWhereNull('user_id')->count(),
            'User accounts' => User::where('id', '!=', $admin->id)->count(),
        ];
    }

    /**
     * Every uploaded file the doomed records point at, except anything the
     * admin's own account uses.
     *
     * @return list<string>
     */
    private function uploadedFiles(User $admin): array
    {
        $adminPlayers = Player::query()->where('user_id', $admin->id);

        $keep = array_filter([
            $admin->photo_path,
            ...(clone $adminPlayers)->pluck('photo_path')->all(),
        ]);

        $paths = collect([
            Tournament::query()->whereNotNull('poster_path')->pluck('poster_path'),
            PaymentDetail::query()->whereNotNull('proof_of_payment')->pluck('proof_of_payment'),
            ApplicationPlayer::query()->whereNotNull('id_document_path')->pluck('id_document_path'),
            Player::query()->whereNotNull('photo_path')->whereNotIn('id', (clone $adminPlayers)->select('id'))->pluck('photo_path'),
            User::query()->whereNotNull('photo_path')->where('id', '!=', $admin->id)->pluck('photo_path'),
        ])->flatten()->unique()->reject(fn (string $path) => in_array($path, $keep, true));

        return $paths->values()->all();
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function report(User $admin, array $summary, int $files): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('All data cleared.');
        $this->command->table(['Removed', 'Count'], array_map(fn (string $label, int $count): array => [$label, $count], array_keys($summary), $summary));
        $this->command->line("{$files} uploaded files deleted. ".Category::count().' category templates kept.');
        $this->command->line("Only {$admin->email} remains; its password is unchanged.");
    }
}
