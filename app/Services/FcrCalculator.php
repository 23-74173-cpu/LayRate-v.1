<?php

namespace App\Services;

use App\Models\Cage;
use App\Models\FeedConsumptionLog;
use App\Models\ProductionLog;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            ->real()
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
            ->real()
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
     * All-time feed + egg-mass totals for the FCR header cards.
     *
     * Same row sets and the same per-log weight arithmetic as the timeline
     * builders (real() scope, cage scoping, size weights with fallback), but
     * as two SQL aggregates with no model hydration and no date bound — so
     * the header cards show what they showed before the timeline fetch was
     * windowed. Only float summation order (SQL vs PHP) and the old
     * per-period rounding can shift the last displayed digit.
     *
     * @return array{feed_kg: float, egg_mass_kg: float}
     */
    public static function allTimeTotals(?Cage $cage = null): array
    {
        $feedQuery = FeedConsumptionLog::query();
        if ($cage) {
            $feedQuery->where('cage_id', $cage->id);
        } else {
            $feedQuery->whereHas('cage', fn ($q) => $q->where('is_active', 1));
        }
        $feedKg = (float) $feedQuery->sum('feed_consumed_kg');

        $weights = Setting::eggWeights();
        $bindings = [
            (float) $weights['small'], (float) $weights['medium'],
            (float) $weights['large'], (float) $weights['jumbo'],
            (float) $weights['fallback'],
        ];

        $perLog = DB::table('production_logs as pl')
            ->where('pl.is_demo', false)
            ->selectRaw(
                'pl.id, pl.egg_count, COUNT(esl.id) AS size_rows, ' .
                'COALESCE(SUM(esl.`count` * CASE esl.egg_size ' .
                "WHEN 'small' THEN ? WHEN 'medium' THEN ? WHEN 'large' THEN ? WHEN 'jumbo' THEN ? ELSE ? END), 0) AS sized_grams",
                $bindings
            )
            ->leftJoin('egg_size_logs as esl', 'esl.production_log_id', '=', 'pl.id')
            ->groupBy('pl.id', 'pl.egg_count');

        if ($cage) {
            $perLog->join('cage_slots as cs', 'cs.id', '=', 'pl.cage_slot_id')
                ->where('cs.cage_id', $cage->id);
        } else {
            $perLog->join('cage_slots as cs', 'cs.id', '=', 'pl.cage_slot_id')
                ->join('cages as c', 'c.id', '=', 'cs.cage_id')
                ->where('c.is_active', 1);
        }

        $totalGrams = (float) (DB::query()->fromSub($perLog, 'sized')
            ->selectRaw(
                'SUM(CASE WHEN sized.size_rows = 0 THEN sized.egg_count * ? ELSE sized.sized_grams END) AS total_grams',
                [(float) $weights['fallback']]
            )
            ->value('total_grams') ?? 0);

        return ['feed_kg' => $feedKg, 'egg_mass_kg' => $totalGrams / 1000];
    }

    /**
     * Timeline of FCR per period (day/week/month) across all active cages.
     *
     * $since is optional and defaults to unbounded (existing callers/tests
     * are unaffected) — but every current caller (FeedController's FCR tab)
     * does call this with no bound at all, meaning it loads every
     * production_logs + feed_consumption_logs row ever recorded, with
     * eggSizeLogs eager-loaded per production row, on every view of that
     * tab. Weighted egg-mass-per-size is business-critical enough that this
     * intentionally does NOT change the SQL that computes it — only adds
     * the option to bound the row-level fetch by date, which the caller can
     * opt into once a sensible default window is decided (see the
     * accompanying report — this is flagged, not silently changed).
     *
     * @return Collection Each item: period, label, feed_kg, egg_mass_kg, fcr
     */
    public static function timelineAll(string $groupBy, ?Carbon $since = null): Collection
    {
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        $productionLogs = ProductionLog::with('eggSizeLogs')
            ->real()
            ->whereHas('cageSlot.cage', fn ($q) => $q->where('is_active', 1))
            ->when($since, fn ($q) => $q->where('log_date', '>=', $since->toDateString()))
            ->orderBy('log_date')
            ->get();

        $feedLogs = FeedConsumptionLog::whereHas('cage', fn ($q) => $q->where('is_active', 1))
            ->when($since, fn ($q) => $q->where('log_date', '>=', $since->toDateString()))
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
     * $since is optional and defaults to unbounded — see the note on
     * timelineAll() above; the same "flagged, not silently changed" reasoning
     * applies here.
     *
     * @return Collection Each item: period, label, feed_kg, egg_mass_kg, fcr
     */
    public static function timeline(Cage $cage, string $groupBy, ?Carbon $since = null): Collection
    {
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        // Fetch all relevant logs for the cage.
        $productionLogs = ProductionLog::with('eggSizeLogs')
            ->real()
            ->whereHas('cageSlot', fn ($q) => $q->where('cage_id', $cage->id))
            ->when($since, fn ($q) => $q->where('log_date', '>=', $since->toDateString()))
            ->orderBy('log_date')
            ->get();

        $feedLogs = FeedConsumptionLog::where('cage_id', $cage->id)
            ->when($since, fn ($q) => $q->where('log_date', '>=', $since->toDateString()))
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
