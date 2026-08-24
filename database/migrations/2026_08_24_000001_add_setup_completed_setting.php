<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Marks the initial setup wizard as complete so it only auto-runs once
     * on a fresh deployment. Stored in the settings table (default 0).
     *
     * This is intentionally separate from farm_grid_rows / farm_grid_cols,
     * which migrations already seed — the old `$needsOnboarding` check keyed
     * on those being missing, so it never fired on a fresh install.
     */
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            ['key' => 'setup_completed', 'value' => '0', 'label' => 'Initial Setup Completed', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'setup_completed')->delete();
    }
};
