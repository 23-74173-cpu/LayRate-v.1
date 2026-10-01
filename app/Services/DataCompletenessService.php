<?php

namespace App\Services;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\ProductionLog;
use Illuminate\Support\Facades\DB;

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
        $yesterday = $snapshotDate->copy()->subDay()->toDateString();

        $activeCageCodes = Cage::where('is_active', 1)->orderBy('cage_code')->pluck('cage_code')->values();
        $totalActiveCages = $activeCageCodes->count();

        if ($totalActiveCages === 0) {
            $empty = ['logged' => 0, 'total' => 0, 'complete' => true];
            return ['eggs' => $empty, 'environment' => $empty, 'feed' => $empty];
        }

        $cagesWithEggs = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->join('cages', 'cages.id', '=', 'cage_slots.cage_id')
            ->whereIn('cages.cage_code', $activeCageCodes)
            ->whereDate('production_logs.log_date', $today)
            ->where('production_logs.is_demo', false)
            ->distinct('cages.cage_code')
            ->count('cages.cage_code');

        $cagesWithEnv = EnvironmentalLog::query()
            ->join('cages', 'cages.id', '=', 'environmental_logs.cage_id')
            ->whereIn('cages.cage_code', $activeCageCodes)
            ->where('environmental_logs.is_demo', false)
            ->whereBetween(DB::raw('DATE(environmental_logs.recorded_at)'), [$yesterday, $today])
            ->select('cages.cage_code', 'environmental_logs.recorded_at')
            ->get()
            ->map(fn ($r) => [
                'cage_code' => $r->cage_code,
                'reporting_date' => ReportingDateService::reportingDateFor($r->recorded_at)->toDateString(),
            ])
            ->where('reporting_date', $today)
            ->pluck('cage_code')->unique()->count();

        $cagesWithFeed = FeedConsumptionLog::query()
            ->join('cages', 'cages.id', '=', 'feed_consumption_logs.cage_id')
            ->whereIn('cages.cage_code', $activeCageCodes)
            ->whereDate('feed_consumption_logs.log_date', $today)
            ->distinct()
            ->pluck('cages.cage_code')
            ->count();

        return [
            'eggs'        => ['logged' => $cagesWithEggs, 'total' => $totalActiveCages, 'complete' => $cagesWithEggs >= $totalActiveCages],
            'environment' => ['logged' => $cagesWithEnv,  'total' => $totalActiveCages, 'complete' => $cagesWithEnv >= $totalActiveCages],
            'feed'        => ['logged' => $cagesWithFeed, 'total' => $totalActiveCages, 'complete' => $cagesWithFeed >= $totalActiveCages],
        ];
    }
}
