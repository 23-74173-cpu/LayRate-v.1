<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mortality_logs has no index at all on log_date. Two distinct query
     * shapes touch it:
     *   1. MortalityController::index()/logs() — the main mortality page —
     *      ORDER BY log_date DESC with NO cage_id filter (global listing),
     *      plus a global WHERE DATE(log_date) = today() aggregate.
     *   2. Controller::checkMortalitySpike() — WHERE cage_id = ? AND
     *      DATE(log_date) = ? (cage-scoped dedup check).
     * A standalone index on log_date serves (1) directly and is the more
     * consequential fix, since that's the primary user-facing listing and
     * MySQL cannot use any composite for an ORDER BY that isn't prefixed by
     * an equality filter it's leading on. (2) still benefits from the
     * existing FK-implicit index on cage_id — mortality entries are
     * low-frequency manual writes (not the ~1Hz sensor path), so a second,
     * more specialized composite index isn't worth the extra write cost
     * here.
     */
    public function up(): void
    {
        Schema::table('mortality_logs', function (Blueprint $table) {
            $table->index('log_date', 'mortality_logs_log_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('mortality_logs', function (Blueprint $table) {
            $table->dropIndex('mortality_logs_log_date_index');
        });
    }
};
