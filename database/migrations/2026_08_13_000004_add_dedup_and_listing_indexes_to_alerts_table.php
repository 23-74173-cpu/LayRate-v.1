<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * alerts has two real, distinct hot query shapes — neither is served
     * well by a plain standalone index on is_read alone (a boolean-ish
     * tinyint column has terrible selectivity on its own; MySQL will often
     * ignore a lone index like that and scan anyway):
     *
     *  1. The dedup "already alerted today?" check, run on every
     *     alert-eligible write across 6 call sites (SensorIngestion x2,
     *     EnvironmentAlertService, Controller::checkMortalitySpike,
     *     FeedController, PreOrderController):
     *       WHERE cage_id = ? AND alert_type = ? AND is_read = 0
     *         AND DATE(triggered_at) = ?
     *     A composite covering the three equality predicates lets MySQL
     *     seek straight to the handful of matching rows instead of
     *     scanning every alert ever raised for that cage. DATE() wrapping
     *     triggered_at means the index can't range-seek on the date part,
     *     but it doesn't need to — the composite already narrows to a tiny
     *     row set via the three leading equalities before that filter
     *     even runs.
     *
     *  2. The notifications/alerts listing (AlertController::index/
     *     table/apiIndex) — ORDER BY triggered_at DESC, optionally
     *     WHERE is_read = false, no cage_id filter. This is unscoped, so
     *     the composite above (led by cage_id) is invisible to it; it
     *     needs its own index on triggered_at.
     *
     * This table grows without bound for as long as the system runs, so
     * both of these compound over time exactly like production_logs.
     */
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->index(['cage_id', 'alert_type', 'is_read', 'triggered_at'], 'alerts_dedup_check_index');
            $table->index('triggered_at', 'alerts_triggered_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('alerts_dedup_check_index');
            $table->dropIndex('alerts_triggered_at_index');
        });
    }
};
