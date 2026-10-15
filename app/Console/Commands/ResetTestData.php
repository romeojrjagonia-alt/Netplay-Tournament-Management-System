<?php

namespace App\Console\Commands;

use Database\Seeders\DeployTestDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes every record in the database and seeds fresh test data, for
 * resetting a staging/demo deployment (e.g. from a Railway shell):
 *
 *   php artisan netplay:reset-test-data --force
 *
 * The schema and the `migrations` table are kept. Uploaded files on disk
 * (ID documents, payment proofs) are NOT deleted.
 */
class ResetTestData extends Command
{
    /**
     * Every seeded account gets this password.
     */
    public const PASSWORD = 'password123';

    protected $signature = 'netplay:reset-test-data {--force : Skip the confirmation prompt (required when not interactive)}';

    protected $description = 'Delete ALL database records, then create fresh test data (accounts use the password "password123")';

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();

        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->error('Refusing to wipe the database without --force.');

                return self::FAILURE;
            }

            $this->warn("This deletes EVERY record in the \"{$database}\" database.");

            if (! $this->confirm('Wipe the database and create test data?')) {
                $this->line('Cancelled.');

                return self::FAILURE;
            }
        }

        $tables = collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn (string $table) => $table === 'migrations' || str_starts_with($table, 'sqlite_'))
            ->values();

        Schema::disableForeignKeyConstraints();

        try {
            $tables->each(fn (string $table) => DB::table($table)->truncate());
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info("Wiped {$tables->count()} tables in \"{$database}\".");

        // The seeder reads its password from the environment; put the
        // environment back afterwards so nothing else in this process sees it.
        $key = 'TEST_SEED_PASSWORD';
        $previousEnv = getenv($key);
        $hadEnv = array_key_exists($key, $_ENV);
        $previousEnvVar = $_ENV[$key] ?? null;
        $hadServer = array_key_exists($key, $_SERVER);
        $previousServerVar = $_SERVER[$key] ?? null;

        putenv($key.'='.self::PASSWORD);
        $_ENV[$key] = self::PASSWORD;
        $_SERVER[$key] = self::PASSWORD;

        try {
            $this->call('db:seed', ['--class' => DeployTestDataSeeder::class, '--force' => true]);
        } finally {
            putenv($previousEnv === false ? $key : $key.'='.$previousEnv);

            if ($hadEnv) {
                $_ENV[$key] = $previousEnvVar;
            } else {
                unset($_ENV[$key]);
            }

            if ($hadServer) {
                $_SERVER[$key] = $previousServerVar;
            } else {
                unset($_SERVER[$key]);
            }
        }

        $this->newLine();
        $this->info('Test accounts (password for all: '.self::PASSWORD.')');
        $this->table(['Role', 'Email'], [
            ['admin', 'admin@seed.netplay.test'],
            ['umpire', 'umpire1@seed.netplay.test'],
            ['umpire', 'umpire2@seed.netplay.test'],
            ['player', 'player1@seed.netplay.test'],
            ['player', 'player2@seed.netplay.test'],
            ['player', 'player3@seed.netplay.test'],
        ]);

        return self::SUCCESS;
    }
}
