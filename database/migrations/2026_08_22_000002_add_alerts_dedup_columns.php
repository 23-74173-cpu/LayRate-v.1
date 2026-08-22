<?php

use App\Models\Alert;
use App\Services\ReportingDateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Give `alerts` a real DB-level "one alert per (scope, type[, size]) per
     * reporting day" guarantee, independent of application logic.
     *
     * Two plain columns written by the app:
     *   - dedup_key:  "{cage_id|0}:{alert_type}[:{size}]"  (see Alert::dedupKey)
     *   - alert_day:  the reporting date (Y-m-d) the alert belongs to
     * UNIQUE(dedup_key, alert_day) is the backstop under every exists-check.
     *
     * Existing rows are reclassified here so old and new rows share the same
     * key grammar (final alert_type values post Prompt-4 split), then any
     * pre-existing duplicates are removed before the index is added.
     */
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->date('alert_day')->nullable()->after('triggered_at');
            $table->string('dedup_key', 120)->nullable()->after('alert_day');
        });

        $sizePattern = '/\b(small|medium|large|jumbo|unsorted)\b/';
        $deathDatePattern = '/died on (\d{4}-\d{2}-\d{2})/';
        $appTz = config('app.timezone', 'UTC');

        DB::table('alerts')->orderBy('id')->select(['id', 'cage_id', 'alert_type', 'message', 'triggered_at'])
            ->get()
            ->each(function ($alert) use ($sizePattern, $deathDatePattern, $appTz) {
                $message = strtolower($alert->message);

                // Day: mortality rows are dated by their deaths' log_date;
                // everything else by the reporting day of when it fired.
                $day = null;
                if ($alert->alert_type === 'mortality_spike') {
                    preg_match($deathDatePattern, $message, $m);
                    if (! empty($m[1])) {
                        $day = $m[1];
                    }
                }
                if ($day === null) {
                    $firedAt = Carbon::parse($alert->triggered_at, $appTz);
                    $day = ReportingDateService::reportingDateFor($firedAt)->toDateString();
                }

                // Key: cage scope + type; size scope for the two per-size types.
                $size = in_array($alert->alert_type, ['low_stock_eggs', 'stock_depletion'], true)
                    && preg_match($sizePattern, $message, $m2)
                    ? $m2[1]
                    : null;

                $key = Alert::dedupKey($alert->cage_id, $alert->alert_type, $size);

                DB::table('alerts')->where('id', $alert->id)->update([
                    'alert_day' => $day,
                    'dedup_key' => $key,
                ]);
            });

        // De-duplicate anything that now collides — keep the newest per identity.
        $dupes = DB::table('alerts')
            ->select('dedup_key', 'alert_day', DB::raw('MAX(id) as keep_id'))
            ->whereNotNull('dedup_key')
            ->groupBy('dedup_key', 'alert_day')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dup) {
            DB::table('alerts')
                ->where('dedup_key', $dup->dedup_key)
                ->where('alert_day', $dup->alert_day)
                ->where('id', '<>', $dup->keep_id)
                ->delete();
        }

        Schema::table('alerts', function (Blueprint $table) {
            $table->unique(['dedup_key', 'alert_day'], 'alerts_dedup_key_day_unique');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropUnique('alerts_dedup_key_day_unique');
            $table->dropColumn(['dedup_key', 'alert_day']);
        });
    }
};