<?php

namespace App\Services;

use App\Models\ProductionLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared day/week/month aggregation logic for production logs.
 * Used by the egg production history page and the FCR calculator.
 */
class ProductionTimelineService
{
    /**
     * Aggregate production logs by day, week, or month.
     *
     * @param  string  $groupBy  'day', 'week', or 'month'
     * @param  \Closure|null  $queryModifier  Optional callback to constrain the query
     */
    public static function aggregate(string $groupBy, ?\Closure $queryModifier = null): Collection
    {
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        $select = match ($groupBy) {
            'week' => "DATE_FORMAT(log_date, '%Y-%u') as period, MIN(log_date) as period_start, COUNT(*) as records",
            'month' => "DATE_FORMAT(log_date, '%Y-%m') as period, MIN(log_date) as period_start, COUNT(*) as records",
            default => 'log_date as period, log_date as period_start, COUNT(*) as records',
        };

        $query = ProductionLog::query()
            ->selectRaw("{$select}, SUM(egg_count) as total_eggs")
            ->groupBy('period')
            ->orderByDesc('period_start');

        if ($queryModifier) {
            $queryModifier($query);
        }

        return $query->get()->map(fn ($row) => [
            'period' => $row->period,
            'period_start' => $row->period_start,
            'total_eggs' => (int) $row->total_eggs,
            'records' => (int) $row->records,
            'label' => self::periodLabel($row->period, $groupBy),
        ]);
    }

    /**
     * Determine the period key for a given date using the same bucketing logic.
     */
    public static function periodForDate(Carbon $date, string $groupBy): string
    {
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        return match ($groupBy) {
            'week' => $date->format('Y').'-'.$date->format('W'),
            'month' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    public static function periodLabel(string $period, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => 'Week '.substr($period, 5).', '.substr($period, 0, 4),
            'month' => Carbon::parse($period.'-01')->format('F Y'),
            default => Carbon::parse($period)->format('M j, Y'),
        };
    }
}
