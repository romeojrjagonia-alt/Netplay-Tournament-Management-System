<?php

namespace App\Console\Commands;

use Database\Seeders\TestAccountsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes every record in the database and creates the test login accounts
 * (no events or other data), for resetting a staging/demo deployment, e.g.
 * from a Railway shell:
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

    protected $description = 'Delete ALL database records, then create the test accounts only (password "password123")';

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();

        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->error('Refusing to wipe the database without --force.');

                return self::FAILURE;
            }

            $this->warn("This deletes EVERY record in the \"{$database}\" database.");

            if (! $this->confirm('Wipe the database and create the test accounts?')) {
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

        (new TestAccountsSeeder(self::PASSWORD))->run();

        $this->newLine();
        $this->info('Test accounts (password for all: '.self::PASSWORD.')');
        $this->table(['Role', 'Email'], collect(TestAccountsSeeder::ACCOUNTS)
            ->map(fn (array $account) => [$account[2], $account[0].'@'.TestAccountsSeeder::EMAIL_DOMAIN])
            ->all());

        return self::SUCCESS;
    }
}
