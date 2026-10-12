<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Signup used to keep the email only on the user. Copy it onto the linked
     * player so walk-in registrations can match existing account holders.
     */
    public function up(): void
    {
        DB::statement(
            'UPDATE players SET email = (SELECT email FROM users WHERE users.id = players.user_id) '
            .'WHERE email IS NULL AND user_id IS NOT NULL'
        );
    }

    /**
     * The copied emails are indistinguishable from ones players entered
     * themselves, so there is nothing safe to undo.
     */
    public function down(): void {}
};
