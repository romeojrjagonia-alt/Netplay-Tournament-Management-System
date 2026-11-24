<?php

namespace Database\Seeders;

use App\Models\ApplicationPlayer;
use App\Models\Matches;
use App\Models\PaymentDetail;
use App\Models\Player;
use App\Models\PlayerApplication;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the demo and test data the seeders create, and nothing else. Real
 * events, players and accounts are left alone. The seeded admin account
 * (Admin@Netplay.com, see AdminSeeder) always stays. It is never called from
 * DatabaseSeeder:
 *
 *   php artisan db:seed --class=ClearSeededDataSeeder
 *
 * What counts as seeded, because the seeders leave these marks:
 * - tournaments named by LiveDemoSeeder or StatisticSeedingDemoSeeder, or
 *   starting with "[TEST] " (the older deployment seeder), and a few older
 *   demo events whose names say they are demos;
 * - accounts and players with an @demo.netplay.test or @seed.netplay.test
 *   address, and the handful of older demo accounts listed below;
 * - the walk-in players those tournaments created, but only when they have
 *   no account and nothing left to their name once the tournaments are gone.
 *
 * Deliberately NOT touched: category templates, events with ordinary names
 * (even ones an old seeder used, like "Inato Tournament", since a real event
 * may share that name), and any player who has real history elsewhere. A real
 * person who registered for a demo tournament only loses that registration.
 * To erase everything instead, use WipeAllDataSeeder.
 *
 * Safety: it asks for confirmation first (or CLEAR_DATA_CONFIRMED=true where
 * nobody can be asked; always required on production), and refuses to run if
 * the seeded admin does not exist.
 */
class ClearSeededDataSeeder extends Seeder
{
    /** Name of the older deployment seeder's tournaments. */
    public const TEST_PREFIX = '[TEST] ';

    /**
     * Demo events from seeders that no longer exist; their names say "demo".
     *
     * @var list<string>
     */
    public const OLDER_TOURNAMENTS = [
        'Round Robin Regen Demo',
        'Single Elim Regen Demo',
        'Bracket Demo Open',
        'Schedule Demo Open',
    ];

    /**
     * Email domains only seeders use.
     *
     * @var list<string>
     */
    public const SEED_DOMAINS = [
        LiveDemoSeeder::EMAIL_DOMAIN,
        'seed.netplay.test',
    ];

    /**
     * Accounts made by older demo seeders.
     *
     * @var list<string>
     */
    public const OLDER_ACCOUNTS = [
        'schedule-admin@example.com',
        'schedule-umpire@example.com',
        'demo-admin@netplay.test',
        'demo-umpire@netplay.test',
        'demo-umpire2@netplay.test',
        'demo-player@netplay.test',
    ];

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

        $tournaments = $this->seededTournaments()->get();
        $users = $this->seededUsers($admin)->get();

        // Settled up front, while the links between records still exist.
        $applicationIds = PlayerApplication::query()
            ->whereIn('tournament_category_id', DB::table('tournament_categories')->whereIn('tournament_id', $tournaments->modelKeys())->select('id'))
            ->pluck('id');

        $candidatePlayerIds = ApplicationPlayer::query()->whereIn('application_id', $applicationIds)->pluck('player_id')
            ->merge($this->seededPlayers()->pluck('id'))
            ->merge(Player::query()->whereIn('user_id', $users->modelKeys())->pluck('id'))
            ->unique()
            ->values();

        $files = collect([
            $tournaments->pluck('poster_path'),
            PaymentDetail::query()->whereIn('application_id', $applicationIds)->pluck('proof_of_payment'),
            ApplicationPlayer::query()->whereIn('application_id', $applicationIds)->pluck('id_document_path'),
            Player::query()->whereIn('id', $candidatePlayerIds)->pluck('photo_path'),
            $users->pluck('photo_path'),
        ])->flatten()->filter()->unique()->values();

        $counts = [
            'Tournaments' => $tournaments->count(),
            'Applications' => $applicationIds->count(),
            'Matches' => Matches::query()->whereIn('tournament_category_id', DB::table('tournament_categories')->whereIn('tournament_id', $tournaments->modelKeys())->select('id'))->count(),
            'User accounts' => $users->count(),
            'Players' => 0,
        ];

        DB::transaction(function () use ($tournaments, $users, $candidatePlayerIds, &$counts): void {
            // Cascades through categories offered, applications, entries,
            // matches, games, rallies and payments.
            Tournament::query()->whereIn('id', $tournaments->modelKeys())->delete();

            // Their players are kept (account removed) until judged below.
            User::query()->whereIn('id', $users->modelKeys())->delete();

            // Only guests with nothing left to their name go: a player with
            // an entry in a real tournament, or a real account, stays.
            $counts['Players'] = Player::query()
                ->whereIn('id', $candidatePlayerIds)
                ->whereNull('user_id')
                ->whereDoesntHave('applicationPlayers')
                ->delete();

            DB::table('sessions')->whereIn('user_id', $users->modelKeys())->delete();
            DB::table('password_reset_tokens')->whereIn('email', $users->pluck('email'))->delete();
        });

        $deletable = $this->unreferenced($files);

        Storage::disk('public')->delete($deletable->all());

        $this->report($counts, $deletable->count());
    }

    /**
     * @return Builder<Tournament>
     */
    private function seededTournaments(): Builder
    {
        return Tournament::query()->where(function (Builder $query): void {
            $query->whereIn('name', [...LiveDemoSeeder::TOURNAMENTS, StatisticSeedingDemoSeeder::TOURNAMENT, ...self::OLDER_TOURNAMENTS])
                ->orWhere('name', 'like', self::TEST_PREFIX.'%');
        });
    }

    /**
     * Every account the seeders make, never the seeded admin.
     *
     * @return Builder<User>
     */
    private function seededUsers(User $admin): Builder
    {
        return User::query()
            ->where('id', '!=', $admin->id)
            ->where(function (Builder $query): void {
                foreach (self::SEED_DOMAINS as $domain) {
                    $query->orWhere('email', 'like', '%@'.$domain);
                }

                $query->orWhereIn('email', self::OLDER_ACCOUNTS);
            });
    }

    /**
     * @return Builder<Player>
     */
    private function seededPlayers(): Builder
    {
        return Player::query()->where(function (Builder $query): void {
            foreach (self::SEED_DOMAINS as $domain) {
                $query->orWhere('email', 'like', '%@'.$domain);
            }
        });
    }

    /**
     * Files nothing remaining still points at, so one shared by a real record
     * is never deleted from under it.
     *
     * @param  Collection<int, string>  $paths
     * @return Collection<int, string>
     */
    private function unreferenced(Collection $paths): Collection
    {
        if ($paths->isEmpty()) {
            return $paths;
        }

        $inUse = collect([
            Tournament::query()->whereIn('poster_path', $paths)->pluck('poster_path'),
            PaymentDetail::query()->whereIn('proof_of_payment', $paths)->pluck('proof_of_payment'),
            ApplicationPlayer::query()->whereIn('id_document_path', $paths)->pluck('id_document_path'),
            Player::query()->whereIn('photo_path', $paths)->pluck('photo_path'),
            User::query()->whereIn('photo_path', $paths)->pluck('photo_path'),
        ])->flatten();

        return $paths->diff($inUse)->values();
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
            $this->command?->error('This would delete data on a production system. Set CLEAR_DATA_CONFIRMED=true if that is really what you want. Nothing was deleted.');

            return false;
        }

        if ($this->command === null) {
            return true;
        }

        if (! $this->command->confirm('This permanently deletes the seeded demo and test data (real data is left alone). Continue?', false)) {
            $this->command->warn('Cancelled. Nothing was deleted.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function report(array $counts, int $files): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('Seeded data cleared.');
        $this->command->table(['Removed', 'Count'], array_map(fn (string $label, int $count): array => [$label, $count], array_keys($counts), $counts));
        $this->command->line("{$files} uploaded files deleted. Real events, players and accounts, the category templates and ".AdminSeeder::EMAIL.' were not touched.');
    }
}
