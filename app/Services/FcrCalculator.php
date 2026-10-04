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

        $eggMassKg = self::eggMassByDate($cage, false, $start->toDateString(), $end->toDateString())->sum();

        if ($feedKg <= 0 || $eggMassKg <= 0) {
            return null;
        }

        return round($feedKg / $eggMassKg, 2);
    }

    /**
     * Egg mass (kg) per log date, computed in SQL with exactly the rule of
     * eggMassForLog(): a log with egg size rows weighs SUM(count x size
     * weight, fallback weight for any other size); a log without size rows
     * weighs egg_count x fallback weight. Same row set as the old Eloquent
     * fetches: real logs only, slot must exist, scoped to one cage or to
     * active cages. Returns one row per day instead of hydrating every
     * production log and its size rows (about 23,000 models for an 11-month
     * FCR view), which made the FCR panel take ~3 s. Only float summation
     * order can differ, far below the 3-decimal / 2-decimal display rounding.
     *
     * @return Collection<string, float>  'Y-m-d' => kg
     */
    private static function eggMassByDate(?Cage $cage, bool $activeCagesOnly, ?string $from = null, ?string $to = null): Collection
    {
        $weights = Setting::eggWeights();

        $perLog = DB::table('production_logs as pl')
            ->where('pl.is_demo', false)
            ->selectRaw(
                'pl.id, pl.log_date, pl.egg_count, COUNT(esl.id) AS size_rows, ' .
                'COALESCE(SUM(esl.`count` * CASE esl.egg_size ' .
                "WHEN 'small' THEN ? WHEN 'medium' THEN ? WHEN 'large' THEN ? WHEN 'jumbo' THEN ? ELSE ? END), 0) AS sized_grams",
                [
                    (float) $weights['small'], (float) $weights['medium'],
                    (float) $weights['large'], (float) $weights['jumbo'],
                    (float) $weights['fallback'],
                ]
            )
            ->leftJoin('egg_size_logs as esl', 'esl.production_log_id', '=', 'pl.id')
            ->join('cage_slots as cs', 'cs.id', '=', 'pl.cage_slot_id')
            ->when($cage, fn ($q) => $q->where('cs.cage_id', $cage->id))
            ->when($activeCagesOnly, fn ($q) => $q->join('cages as c', 'c.id', '=', 'cs.cage_id')->where('c.is_active', 1))
            ->when($from, fn ($q) => $q->where('pl.log_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('pl.log_date', '<=', $to))
            ->groupBy('pl.id', 'pl.log_date', 'pl.egg_count');

        return DB::query()->fromSub($perLog, 'sized')
            ->selectRaw(
                'sized.log_date, SUM(CASE WHEN sized.size_rows = 0 THEN sized.egg_count * ? ELSE sized.sized_grams END) AS grams',
                [(float) $weights['fallback']]
            )
            ->groupBy('sized.log_date')
            ->orderBy('sized.log_date')
            ->pluck('grams', 'log_date')
            ->map(fn ($grams) => (float) $grams / 1000);
    }

    /**
     * Egg mass per period (day/week/month key), from eggMassByDate().
     *
     * @return Collection<string, float>
     */
    private static function eggMassByPeriod(Collection $byDate, string $groupBy): Collection
    {
        $byPeriod = [];
        foreach ($byDate as $date => $kg) {
            $period = ProductionTimelineService::periodForDate(Carbon::parse($date), $groupBy);
            $byPeriod[$period] = ($byPeriod[$period] ?? 0) + $kg;
        }

        return collect($byPeriod);
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

        $eggMassKg = self::eggMassByDate(null, true, $start->toDateString(), $end->toDateString())->sum();

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
     * are unaffected); FeedController's FCR tab passes a recent window.
     * Egg mass comes from eggMassByDate() (one SQL row per day, same
     * per-size weight rule as eggMassForLog()) instead of hydrating every
     * production log with its egg size rows.
     *
     * @return Collection Each item: period, label, feed_kg, egg_mass_kg, fcr
     */
    public static function timelineAll(string $groupBy, ?Carbon $since = null): Collection
    {
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        $feedLogs = FeedConsumptionLog::whereHas('cage', fn ($q) => $q->where('is_active', 1))
            ->when($since, fn ($q) => $q->where('log_date', '>=', $since->toDateString()))
            ->orderBy('log_date')
            ->get();

        $feedByPeriod = $feedLogs->groupBy(
            fn ($log) => ProductionTimelineService::periodForDate($log->log_date, $groupBy)
        )->map(fn ($group) => $group->sum('feed_consumed_kg'));

        $eggMassByPeriod = self::eggMassByPeriod(
            self::eggMassByDate(null, true, $since?->toDateString()),
            $groupBy
        );

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
     * $since is optional and defaults to unbounded; egg mass comes from
     * eggMassByDate(), as in timelineAll() above.
     *
     * @return Collection Each item: period, label, feed_kg, egg_mass_kg, fcr
     */
    public static function timeline(Cage $cage, string $groupBy, ?Carbon $since = null): Collection
    {
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        $feedLogs = FeedConsumptionLog::where('cage_id', $cage->id)
            ->when($since, fn ($q) => $q->where('log_date', '>=', $since->toDateString()))
            ->orderBy('log_date')
            ->get();

        // Group feed and egg mass by period using the shared bucketing logic.
        $feedByPeriod = $feedLogs->groupBy(
            fn ($log) => ProductionTimelineService::periodForDate($log->log_date, $groupBy)
        )->map(fn ($group) => $group->sum('feed_consumed_kg'));

        $eggMassByPeriod = self::eggMassByPeriod(
            self::eggMassByDate($cage, false, $since?->toDateString()),
            $groupBy
        );

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
