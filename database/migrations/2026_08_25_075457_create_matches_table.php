<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_category_id')->constrained()->cascadeOnDelete();
            $table->string('bracket_type');
            $table->unsignedInteger('round');
            $table->unsignedInteger('position');
            $table->foreignId('winner_application_id')->nullable()->constrained('player_applications')->nullOnDelete();
            $table->string('status');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('court')->nullable();
            $table->foreignId('assigned_umpire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
