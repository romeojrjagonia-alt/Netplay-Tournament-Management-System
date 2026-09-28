<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the tournaments.status enum (registration_open /
     * registration_closed / upcoming — which conflated visibility with
     * registration timing) with a simple draft/published lifecycle.
     * "Concluded" is derived from date_end rather than stored, and
     * registration-open/closed is derived from registration_start/end —
     * see Tournament::acceptingRegistrations().
     */
    public function up(): void
    {
        // Each step is guarded so this migration can also complete cleanly
        // from the partially-applied state left behind on MariaDB < 10.5.2
        // and MySQL < 8.0.3, where the final rename below used to fail.
        if (Schema::hasColumn('tournaments', 'status') && ! Schema::hasColumn('tournaments', 'lifecycle_status')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->enum('lifecycle_status', ['draft', 'published'])->default('draft')->after('status');
            });

            DB::table('tournaments')
                ->whereIn('status', ['registration_open', 'registration_closed'])
                ->update(['lifecycle_status' => 'published']);

            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('tournaments', 'lifecycle_status') && ! Schema::hasColumn('tournaments', 'status')) {
            $this->renameColumnSafely('lifecycle_status', 'status', "enum('draft', 'published') not null default 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->enum('old_status', ['registration_open', 'registration_closed', 'upcoming'])->default('upcoming')->after('status');
        });

        DB::table('tournaments')
            ->where('status', 'published')
            ->update(['old_status' => 'registration_open']);

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        $this->renameColumnSafely('old_status', 'status', "enum('registration_open', 'registration_closed', 'upcoming') not null default 'upcoming'");
    }

    /**
     * Rename a `tournaments` column, working around a MySQL/MariaDB-only
     * bug: on MariaDB < 10.5.2 (and MySQL < 8.0.3), Laravel's renameColumn()
     * falls back to reconstructing the full column definition from
     * information_schema, which double-quotes an already-quoted enum
     * default and gets rejected by the server (error 1067: invalid default
     * value). A raw `CHANGE` statement is valid on every MySQL/MariaDB
     * version and sidesteps that reconstruction entirely. SQLite (used in
     * tests) has no such bug, so it keeps using the standard schema builder.
     */
    private function renameColumnSafely(string $from, string $to, string $mysqlColumnDefinition): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("alter table tournaments change {$from} {$to} {$mysqlColumnDefinition}");

            return;
        }

        Schema::table('tournaments', fn (Blueprint $table) => $table->renameColumn($from, $to));
    }
};
