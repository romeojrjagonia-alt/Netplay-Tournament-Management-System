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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('format', ['single_elimination', 'round_robin', 'double_elimination']);
            $table->string('sport');
            $table->string('scoring_system');
            $table->date('date_start');
            $table->date('date_end');
            $table->string('time')->nullable();
            $table->string('specific_days')->nullable();
            $table->date('registration_start');
            $table->date('registration_end');
            $table->enum('status', ['registration_open', 'registration_closed', 'upcoming']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
