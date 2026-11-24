<?php

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Hash;

test('the admin seeder creates a verified admin account', function () {
    $this->seed(AdminSeeder::class);

    $admin = User::where('email', 'Admin@Netplay.com')->firstOrFail();

    expect($admin->role)->toBe('admin')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Netplay@062020', $admin->password))->toBeTrue();
});

test('running the admin seeder again keeps one account and its current password', function () {
    $this->seed(AdminSeeder::class);
    User::where('email', 'Admin@Netplay.com')->firstOrFail()->update(['password' => 'ChangedLater#1']);

    $this->seed(AdminSeeder::class);

    expect(User::where('email', 'Admin@Netplay.com')->count())->toBe(1)
        ->and(Hash::check('ChangedLater#1', User::firstWhere('email', 'Admin@Netplay.com')->password))->toBeTrue();
});

test('the database seeder creates the admin', function () {
    $this->seed();

    expect(User::where('email', 'Admin@Netplay.com')->where('role', 'admin')->exists())->toBeTrue();
});
