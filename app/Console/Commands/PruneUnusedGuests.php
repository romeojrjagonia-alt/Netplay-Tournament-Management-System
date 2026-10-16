<?php

namespace App\Console\Commands;

use App\Actions\Players\PruneUnusedGuests as PruneAction;
use Illuminate\Console\Command;

class PruneUnusedGuests extends Command
{
    protected $signature = 'netplay:prune-guests {--days=7 : Only records older than this many days} {--dry-run : Show the count without deleting}';

    protected $description = 'Delete guest player records that have no account, no entries and no link requests';

    public function handle(PruneAction $prune): int
    {
        $days = max(0, (int) $this->option('days'));

        if ($this->option('dry-run')) {
            $this->info("{$prune->count($days)} unused guest record(s) older than {$days} days would be deleted.");

            return self::SUCCESS;
        }

        $this->info("Deleted {$prune->handle($days)} unused guest record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
