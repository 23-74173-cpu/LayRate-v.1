<?php

use App\Models\ProductionLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            // Default is 'unknown' so that future automated write paths (e.g. real sensor
            // ingestion) are forced to explicitly set 'sensor'. The manual web form sets
            // 'manual' on every store(). Do not rely on the default for sensor data.
            $table->enum('logged_via', ['manual', 'sensor', 'unknown'])
                ->default('unknown')
                ->after('notes');
        });

        // Backfill existing records based on notes heuristics.
        // - Notes mentioning "sensor" or "IR" are treated as sensor-logged.
        // - Notes mentioning "Manual" (including "Manual entry" / "Manual check") are manual.
        // - Everything else defaults to unknown because we cannot determine the source reliably.
        ProductionLog::whereNotNull('notes')
            ->where(function ($q) {
                $q->where('notes', 'like', '%sensor%')
                  ->orWhere('notes', 'like', '%IR%');
            })
            ->update(['logged_via' => 'sensor']);

        ProductionLog::whereNotNull('notes')
            ->where(function ($q) {
                $q->where('notes', 'like', '%Manual%')
                  ->orWhere('notes', 'like', '%manual%');
            })
            ->update(['logged_via' => 'manual']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropColumn('logged_via');
        });
    }
};
