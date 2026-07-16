<?php

namespace App\Services;

use App\Models\Cage;
use App\Models\FeedConsumptionLog;
use App\Models\ProductionLog;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Feed Conversion Ratio (FCR) calculator.
 *
 * FCR = kg feed consumed ÷ kg egg mass produced.
 * Egg mass is estimated from egg counts using configurable average weights
n * per size (EggSizeLog when available, fallback weight otherwise).
 */
class FcrCalculator
{
    /**
     * Calculate estimated egg mass (kg) for a single production log.
     */
    public static function eggMassForLog(ProductionLog $log): float
    {
        $weights = Setting::eggWeights();
        $sizeLogs = $log->eggSizeLogs;

        if ($sizeLogs->isEmpty()) {
            return ($log->egg_count * $weights['fallback']) / 1000;
        }

        $massGrams = 0;
        foreach ($sizeLogs as $sizeLog) {
            $weight = $weights[$sizeLog->egg_size] ?? $weights['fallback'];
            $massGrams += $sizeLog->count * $weight;
        }

        return $massGrams / 1000;
    }

    /**
     * Calculate FCR for a cage over a date range.
     * Returns null when egg mass is zero (undefined ratio).
     */
    public static function forCage(Cage $cage, Carbon $start, Carbon $end): ?float
    {
        $feedKg = FeedConsumptionLog::where('cage_id', $cage->id)
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->sum('feed_consumed_kg');

        $eggMassKg = ProductionLog::with('eggSizeLogs')
            ->whereHas('cageSlot', fn ($q) => $q->where('cage_id', $cage->id))
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->sum(fn ($log) => self::eggMassForLog($log));

        if ($feedKg <= 0 || $eggMassKg <= 0) {
            return null;
        }

        return round($feedKg / $eggMassKg, 2);
    }

    /**
     * Calculate FCR across all active cages over a date range.
     * Returns null when egg mass is zero (undefined ratio).
     */
    public static function forAllCages(Carbon $start, Carbon $end): ?float
    {
        $feedKg = FeedConsumptionLog::whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('cage', fn ($q) => $q->where('is_active', 1))
            ->sum('feed_consumed_kg');

        $eggMassKg = ProductionLog::with('eggSizeLogs')
            ->whereHas('cageSlot.cage', fn ($q) => $q->where('is_active', 1))
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->sum(fn ($log) => self::eggMassForLog($log));

        if ($feedKg <= 0 || $eggMassKg <= 0) {
            return null;
        }

        return round($feedKg / $eggMassKg, 2);
    }

    /**
     * Timeline of FCR per period (day/week/month) across all active cages.
     *
     * @return Collection Each item: period, label, feed_kg, egg_mass_kg, fcr
     */
    public static function timelineAll(string $groupBy): Collection
    {
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        $productionLogs = ProductionLog::with('eggSizeLogs')
            ->whereHas('cageSlot.cage', fn ($q) => $q->where('is_active', 1))
            ->orderBy('log_date')
            ->get();

        $feedLogs = FeedConsumptionLog::whereHas('cage', fn ($q) => $q->where('is_active', 1))
            ->orderBy('log_date')
            ->get();

        $feedByPeriod = $feedLogs->groupBy(
            fn ($log) => ProductionTimelineService::periodForDate($log->log_date, $groupBy)
        )->map(fn ($group) => $group->sum('feed_consumed_kg'));

        $eggMassByPeriod = $productionLogs->groupBy(
            fn ($log) => ProductionTimelineService::periodForDate($log->log_date, $groupBy)
        )->map(fn ($group) => $group->sum(fn ($log) => self::eggMassForLog($log)));

        $periods = $feedByPeriod->keys()->merge($eggMassByPeriod->keys())->unique()->sortDesc()->values();

        return $periods->map(function ($period) use ($groupBy, $feedByPeriod, $eggMassByPeriod) {
            $feedKg = (float) ($feedByPeriod[$period] ?? 0);
            $eggMassKg = (float) ($eggMassByPeriod[$period] ?? 0);

            return [
                'period' => $period,
                'label' => ProductionTimelineService::periodLabel($period, $groupBy),
                'feed_kg' => round($feedKg, 2),
                'egg_mass_kg' => round($eggMassKg, 3),
                'fcr' => ($feedKg > 0 && $eggMassKg > 0) ? round($feedKg / $eggMassKg, 2) : null,
            ];
        });
    }

    /**
     * Timeline of FCR per period (day/week/month) for a cage.
     *
     * @return Collection Each item: period, label, feed_kg, egg_mass_kg, fcr
     */
    public static function timeline(Cage $cage, string $groupBy): Collection
    {
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        // Fetch all relevant logs for the cage.
        $productionLogs = ProductionLog::with('eggSizeLogs')
            ->whereHas('cageSlot', fn ($q) => $q->where('cage_id', $cage->id))
            ->orderBy('log_date')
            ->get();

        $feedLogs = FeedConsumptionLog::where('cage_id', $cage->id)
            ->orderBy('log_date')
            ->get();

        // Group feed and egg mass by period using the shared bucketing logic.
        $feedByPeriod = $feedLogs->groupBy(
            fn ($log) => ProductionTimelineService::periodForDate($log->log_date, $groupBy)
        )->map(fn ($group) => $group->sum('feed_consumed_kg'));

        $eggMassByPeriod = $productionLogs->groupBy(
            fn ($log) => ProductionTimelineService::periodForDate($log->log_date, $groupBy)
        )->map(fn ($group) => $group->sum(fn ($log) => self::eggMassForLog($log)));

        // Build union of periods from both feed and production data.
        $periods = $feedByPeriod->keys()->merge($eggMassByPeriod->keys())->unique()->sortDesc()->values();

        return $periods->map(function ($period) use ($groupBy, $feedByPeriod, $eggMassByPeriod) {
            $feedKg = (float) ($feedByPeriod[$period] ?? 0);
            $eggMassKg = (float) ($eggMassByPeriod[$period] ?? 0);

            return [
                'period' => $period,
                'label' => ProductionTimelineService::periodLabel($period, $groupBy),
                'feed_kg' => round($feedKg, 2),
                'egg_mass_kg' => round($eggMassKg, 3),
                'fcr' => ($feedKg > 0 && $eggMassKg > 0) ? round($feedKg / $eggMassKg, 2) : null,
            ];
        });
    }
}
