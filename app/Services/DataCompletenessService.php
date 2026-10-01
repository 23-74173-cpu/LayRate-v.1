<?php

namespace App\Services;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\ProductionLog;

/**
 * Daily data-completeness for the global quick-actions dock checklist.
 *
 * Same queries as DashboardController::buildDashboardData() (kept in sync
 * intentionally), but scoped to the live reporting day and safe to run on
 * every authenticated page via the layouts.app view composer. Returns the
 * exact shape the checklist partial consumes:
 *   ['eggs' => ['logged'=>int,'total'=>int,'complete'=>bool], ...]
 */
class DataCompletenessService
{
    public static function forToday(): array
    {
        $snapshotDate = ReportingDateService::reportingDate();
        $today = $snapshotDate->toDateString();

        $activeCageCodes = Cage::where('is_active', 1)->orderBy('cage_code')->pluck('cage_code')->values();
        $totalActiveCages = $activeCageCodes->count();

        if ($totalActiveCages === 0) {
            $empty = ['logged' => 0, 'total' => 0, 'complete' => true];
            return ['eggs' => $empty, 'environment' => $empty, 'feed' => $empty];
        }

        // log_date is a DATE column: compare it directly (whereDate() wraps
        // it in DATE(), which stops MySQL from using the log_date index).
        $cagesWithEggs = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->join('cages', 'cages.id', '=', 'cage_slots.cage_id')
            ->whereIn('cages.cage_code', $activeCageCodes)
            ->where('production_logs.log_date', $today)
            ->where('production_logs.is_demo', false)
            ->distinct('cages.cage_code')
            ->count('cages.cage_code');

        $cagesWithEnv = self::cagesWithEnvReadings($activeCageCodes, $today);

        $cagesWithFeed = FeedConsumptionLog::query()
            ->join('cages', 'cages.id', '=', 'feed_consumption_logs.cage_id')
            ->whereIn('cages.cage_code', $activeCageCodes)
            ->where('feed_consumption_logs.log_date', $today)
            ->distinct()
            ->pluck('cages.cage_code')
            ->count();

        return [
            'eggs'        => ['logged' => $cagesWithEggs, 'total' => $totalActiveCages, 'complete' => $cagesWithEggs >= $totalActiveCages],
            'environment' => ['logged' => $cagesWithEnv,  'total' => $totalActiveCages, 'complete' => $cagesWithEnv >= $totalActiveCages],
            'feed'        => ['logged' => $cagesWithFeed, 'total' => $totalActiveCages, 'complete' => $cagesWithFeed >= $totalActiveCages],
        ];
    }

    /**
     * How many of the given cages have at least one real environmental
     * reading on the given reporting day (Asia/Manila calendar day).
     *
     * Asked per cage as "is there at least one reading in the reporting-day
     * window?", which the (cage_id, recorded_at) index answers by reading a
     * single row. This used to load every reading from the last two
     * calendar days into PHP and convert each timestamp one by one (with a
     * DHT22 reading every 2 seconds, hundreds of thousands of rows on every
     * page load). Same result: a reading belongs to the reporting day exactly
     * when its recorded_at falls inside reportingDayWindow() for that day.
     * Shared with DashboardController so the two stay in sync.
     */
    public static function cagesWithEnvReadings($cageCodes, string $reportingDate): int
    {
        $window = ReportingDateService::reportingDayWindow($reportingDate);

        return Cage::whereIn('cage_code', $cageCodes)
            ->get(['id'])
            ->filter(fn (Cage $cage) => EnvironmentalLog::where('cage_id', $cage->id)
                ->where('is_demo', false)
                ->whereBetween('recorded_at', $window)
                ->exists())
            ->count();
    }
}
