<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `team_*_ready_at`: the umpire's check that a team is present before the
     * match starts. `result_type`: null for a played match, `walkover` when a
     * team did not show up and the other was awarded the win.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('team_a_ready_at')->nullable();
            $table->timestamp('team_b_ready_at')->nullable();
            $table->string('result_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['team_a_ready_at', 'team_b_ready_at', 'result_type']);
        });
    }
};
