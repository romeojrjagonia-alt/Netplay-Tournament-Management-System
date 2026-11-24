<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * An account's email is the player's contact email. Some players were left
     * with their old address after changing it (e.g. via the admin Users page),
     * so registration forms and notifications used the stale one.
     */
    public function up(): void
    {
        DB::statement(
            'UPDATE players SET email = (SELECT email FROM users WHERE users.id = players.user_id) '
            .'WHERE user_id IS NOT NULL AND (email IS NULL OR email <> (SELECT email FROM users WHERE users.id = players.user_id))'
        );
    }

    /**
     * The previous (stale) addresses are not recoverable, and restoring them
     * would reintroduce the bug.
     */
    public function down(): void {}
};
