<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A player asking to claim the guest player record that was registered
     * under their email. `declined` is the player saying "not me" (never asked
     * again); `approved`/`rejected` are an admin's decision.
     */
    public function up(): void
    {
        Schema::create('player_link_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_link_requests');
    }
};
