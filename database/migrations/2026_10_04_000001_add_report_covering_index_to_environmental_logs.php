<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering index for the report queries that average sensor readings per
 * cage and day (ReportController: production report temp/humidity columns,
 * environment summary, environment chart). Every column those queries read
 * is in the index, so the database scans the index instead of the full rows:
 * about 3x faster on a month of 30-second readings, and the gap grows with
 * the table. ReportController names this index only when it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('environmental_logs', 'env_logs_report_covering')) {
            return;
        }

        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->index(
                ['is_demo', 'cage_id', 'recorded_at', 'temperature_c', 'humidity_pct'],
                'env_logs_report_covering'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('environmental_logs', 'env_logs_report_covering')) {
            return;
        }

        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->dropIndex('env_logs_report_covering');
        });
    }
};
