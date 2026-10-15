<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The login accounts used by `netplay:reset-test-data`: one admin, two
 * umpires and three players, all email-verified. It creates no tournaments,
 * applications or matches. Players get their own player record, as every
 * signed-in player has one.
 */
class TestAccountsSeeder extends Seeder
{
    public const EMAIL_DOMAIN = 'seed.netplay.test';

    /**
     * @var list<array{0: string, 1: string, 2: string}> [email local part, name, role]
     */
    public const ACCOUNTS = [
        ['admin', 'Test Admin', 'admin'],
        ['umpire1', 'Test Umpire One', 'umpire'],
        ['umpire2', 'Test Umpire Two', 'umpire'],
        ['player1', 'Test Player One', 'player'],
        ['player2', 'Test Player Two', 'player'],
        ['player3', 'Test Player Three', 'player'],
    ];

    public function __construct(private string $password = 'password123') {}

    public function run(): void
    {
        foreach (self::ACCOUNTS as [$local, $name, $role]) {
            $user = User::firstOrCreate(['email' => "{$local}@".self::EMAIL_DOMAIN], [
                'name' => $name,
                'password' => $this->password,
                'role' => $role,
                'email_verified_at' => now(),
            ]);

            if ($role === 'player') {
                Player::firstOrCreate(['user_id' => $user->id], [
                    'name' => $name,
                    'email' => $user->email,
                    'gender' => $local === 'player2' ? 'female' : 'male',
                    'birth_date' => now()->subYears(25)->toDateString(),
                    'address' => 'Ozamiz City',
                    'level' => 'Beginner',
                ]);
            }
        }
    }
}
