<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DEPLOYMENT-SAFE — creates default admin and operator accounts.
 *
 * Idempotent: uses firstOrCreate so safe to re-run.
 * Passwords should be changed after first login via /account page.
 * To disable this seeder in a specific deployment, comment out the
 * call in DatabaseSeeder and create users via tinker or registration.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@layrate.local'],
            [
                'name' => 'Farm Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        $this->command->info('Admin user created (admin@layrate.local / password).');

        User::firstOrCreate(
            ['email' => 'operator@layrate.local'],
            [
                'name' => 'Farm Operator',
                'password' => Hash::make('password'),
                'role' => 'operator',
            ]
        );

        $this->command->info('Operator user created (operator@layrate.local / password).');
    }
}
