<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quarantine flag for DemoDataSeeder-generated rows (Jul-Sep 2026).
     *
     * The Mar-Jun 2026 import (notes = 'Import: ...', logged_via = 'unknown')
     * is the device-originated production dataset and must be the sole source
     * for forecasting (ForecastGenerationService) and the live environment
     * view (EnvironmentController::liveData). Demo rows stay in the tables
     * (quarantine, not delete) so nothing referencing them breaks:
     * egg_size_logs.production_log_id cascades on delete and
     * egg_stock_batches.source_production_log_id nulls on delete — both are
     * left untouched by a flag update.
     *
     * Backfill signature (verified against DemoDataSeeder):
     * - production_logs: notes IN ('IR sensor synced', 'Manual check') with
     *   logged_via sensor/manual, log_date 2026-07-18..2026-09-08. Real
     *   sensor ingestion writes notes = 'Sensor reading'; the real import
     *   writes notes = 'Import: ...'. Neither matches.
     * - environmental_logs: is_override = 0 rows recorded 2026-07-29..
     *   2026-09-08 (6 distinct dates). The Mar-Jun real window is entirely
     *   is_override = 1 and is untouched. Future sensor rows default to
     *   false; the backfill is date-bounded so it cannot misflag them.
     */
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('logged_via');
            $table->index('is_demo', 'production_logs_is_demo_index');
        });

        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_override');
            $table->index('is_demo', 'environmental_logs_is_demo_index');
        });

        DB::table('production_logs')
            ->whereIn('notes', ['IR sensor synced', 'Manual check'])
            ->whereIn('logged_via', ['sensor', 'manual'])
            ->whereBetween('log_date', ['2026-07-18', '2026-09-08'])
            ->update(['is_demo' => true]);

        DB::table('environmental_logs')
            ->where('is_override', false)
            ->where('recorded_at', '>=', '2026-07-29 00:00:00')
            ->where('recorded_at', '<', '2026-09-09 00:00:00')
            ->update(['is_demo' => true]);
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropIndex('production_logs_is_demo_index');
            $table->dropColumn('is_demo');
        });

        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->dropIndex('environmental_logs_is_demo_index');
            $table->dropColumn('is_demo');
        });
    }
};
