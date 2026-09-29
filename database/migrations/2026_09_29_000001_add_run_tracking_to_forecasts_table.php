<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Makes every forecast run durable and individually attributable so
     * predictions can later be compared against actual production.
     *
     * Previously GenerateForecastJob deleted every forecast row for the
     * current reporting date before saving, so re-running a forecast
     * destroyed the previous prediction. Comparisons were therefore
     * impossible — there was never more than one run of record.
     *
     * forecast_run_id  ties each prediction to the ForecastRun that produced
     *                  it (run params, status, metrics, timestamps).
     * is_backtest      marks a run whose target dates are at or before the
     *                  reporting date, i.e. a backtest rather than a genuine
     *                  forward prediction.
     * superseded_at    soft-replaces, rather than deletes, an earlier
     *                  prediction for the same (cage, breed, target_date).
     *                  The newest prediction for a date is the one with a
     *                  NULL superseded_at; older ones are retained for
     *                  history and auditing. Supersession is scoped per
     *                  target_date so that a backtest of a past date never
     *                  evicts an unrelated forward forecast.
     */
    public function up(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->foreignId('forecast_run_id')
                ->nullable()
                ->after('id')
                ->constrained('forecast_runs')
                ->nullOnDelete();

            $table->boolean('is_backtest')->default(false)->after('forecast_date');

            $table->timestamp('superseded_at')->nullable()->after('is_backtest');
        });

        Schema::table('forecasts', function (Blueprint $table) {
            // Comparison joins forecasts to production_logs on target_date and
            // repeatedly filters to the live prediction for each date.
            $table->index(['target_date', 'superseded_at'], 'forecasts_target_live_index');
            $table->index(['cage_id', 'breed', 'target_date'], 'forecasts_scope_target_index');
        });
    }

    public function down(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->dropIndex('forecasts_target_live_index');
            $table->dropIndex('forecasts_scope_target_index');
        });

        Schema::table('forecasts', function (Blueprint $table) {
            $table->dropForeign(['forecast_run_id']);
            $table->dropColumn(['forecast_run_id', 'is_backtest', 'superseded_at']);
        });
    }
};
