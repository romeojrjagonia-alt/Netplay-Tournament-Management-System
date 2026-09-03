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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->enum('lifecycle_status', ['draft', 'published'])->default('draft')->after('status');
        });

        DB::table('tournaments')
            ->whereIn('status', ['registration_open', 'registration_closed'])
            ->update(['lifecycle_status' => 'published']);

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->renameColumn('lifecycle_status', 'status');
        });
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

        Schema::table('tournaments', function (Blueprint $table) {
            $table->renameColumn('old_status', 'status');
        });
    }
};
