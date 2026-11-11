<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the system administrator account. Safe to run more than once: an
 * existing account keeps its current password, but is made sure to be a
 * verified admin.
 */
class AdminSeeder extends Seeder
{
    public const EMAIL = 'Admin@Netplay.com';

    public const PASSWORD = 'Netplay@062020';

    public function run(): void
    {
        $admin = User::firstOrNew(['email' => self::EMAIL]);

        if (! $admin->exists) {
            $admin->name = 'Administrator';
            $admin->password = self::PASSWORD;
        }

        $admin->role = 'admin';
        $admin->email_verified_at ??= now();
        $admin->save();
    }
}
