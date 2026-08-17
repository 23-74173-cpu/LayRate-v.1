<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * production_logs has a UNIQUE(cage_slot_id, log_date) index, but every
     * query that filters on log_date alone (dashboard "today"/"yesterday"
     * aggregates, the calendar's whereBetween, the FCR/analytics date-range
     * scans, ORDER BY log_date for "first log ever") cannot use it — MySQL's
     * leftmost-prefix rule means a composite index starting with
     * cage_slot_id is invisible to a query that doesn't filter on
     * cage_slot_id first. This table grows without bound (one row per
     * cage-slot per day, forever), so the table scan this currently forces
     * gets slower every month of operation.
     */
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->index('log_date', 'production_logs_log_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropIndex('production_logs_log_date_index');
        });
    }
};
