<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mark an environmental log as a manual user override so read paths can
     * prefer it over live sensor data. Override rows are written at a fixed
     * noon recorded_at by EnvironmentController::updateLog.
     *
     * No backfill: existing noon rows are indistinguishable from real readings
     * and were never flagged, so they stay unflagged (generic handling).
     */
    public function up(): void
    {
        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->boolean('is_override')->default(false)->after('humidity_pct');
        });
    }

    public function down(): void
    {
        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->dropColumn('is_override');
        });
    }
};