<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * DEPLOYMENT-SAFE — seeds application reference / configuration data.
 *
 * All settings are seeded via their respective migrations:
 *   - 2026_01_01_000013_create_settings_table.php  (temp/hum thresholds)
 *   - 2026_07_01_012849_seed_farm_grid_settings.php (grid rows/cols)
 *   - 2026_07_07_133527_seed_default_egg_weight_settings.php (egg weights)
 *
 * Add new reference data here when it doesn't warrant a dedicated migration.
 * This seeder is a no-op today — it exists as an extension point so
 * DatabaseSeeder can remain production-safe without hardcoding settings.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void {}
}
