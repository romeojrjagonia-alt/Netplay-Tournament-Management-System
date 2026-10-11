<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `draw_position` is the 1-based round-1 slot an admin has placed this
     * entry in before the bracket is generated. Null means "not arranged".
     */
    public function up(): void
    {
        Schema::table('player_applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('draw_position')->nullable()->after('status');
            $table->index(['tournament_category_id', 'draw_position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_applications', function (Blueprint $table) {
            $table->dropIndex(['tournament_category_id', 'draw_position']);
            $table->dropColumn('draw_position');
        });
    }
};
