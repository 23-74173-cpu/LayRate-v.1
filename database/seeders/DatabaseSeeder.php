<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DEPLOYMENT-SAFE — default seeder for all environments.
 *
 * Calls only reference-data seeders (users, config, defaults).
 * This is the only seeder registered in the Laravel framework
 * and the only one invoked by `php artisan db:seed` in CI/CD.
 *
 * For local dev, run demo data separately:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * All seeders in the chain are idempotent (firstOrCreate).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ReferenceDataSeeder::class,
            StructuralDataSeeder::class,
        ]);
    }
}
