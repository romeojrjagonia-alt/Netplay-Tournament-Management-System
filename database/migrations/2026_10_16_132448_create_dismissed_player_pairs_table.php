<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pairs of players an admin judged to be different people, so the
     * "Possible duplicates" list stops suggesting them. The smaller id is
     * always stored in `player_a_id`, so a pair has one row either way round.
     */
    public function up(): void
    {
        Schema::create('dismissed_player_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_a_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('player_b_id')->constrained('players')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['player_a_id', 'player_b_id']);
            $table->index('player_b_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dismissed_player_pairs');
    }
};
