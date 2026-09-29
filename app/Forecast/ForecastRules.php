<?php

namespace App\Forecast;

use App\Services\ReportingDateService;
use Carbon\Carbon;

class ForecastRules
{
    /**
     * The latest allowed start date (30 days from today).
     */
    public static function maxStartDate(): Carbon
    {
        return now()->addDays(30)->endOfDay();
    }

    /**
     * The earliest allowed start date.
     *
     * Past dates are forecastable so a prediction can be produced for a day
     * that has already happened and then scored against the actual production
     * recorded for it. The bound is the LATER of:
     *
     *   - $earliestProductionDate, so a backtest can only target a day that
     *     actually has production to compare against; and
     *   - a fixed five-year lookback, so a very old archive cannot be used to
     *     backtest arbitrarily distant dates.
     *
     * Taking the later of the two is what makes this a genuine bound: taking
     * the earlier would let a user pick a date with no production behind it at
     * all, which has nothing to compare against.
     */
    public static function minStartDate(?Carbon $earliestProductionDate = null): Carbon
    {
        $floor = self::earliestBacktestDate();

        if ($earliestProductionDate === null) {
            return $floor;
        }

        $earliest = $earliestProductionDate->copy()->startOfDay();

        return $earliest->gt($floor) ? $earliest : $floor;
    }

    /**
     * Hard floor for backtesting: five years back, applied when production
     * history reaches further than that.
     */
    public static function earliestBacktestDate(): Carbon
    {
        return now()->subYears(5)->startOfDay();
    }

    /**
     * True when the given date is strictly before the reporting date, i.e. the
     * run scores a completed day rather than predicting one.
     *
     * The reporting date itself is deliberately not a backtest. That day is
     * still in progress and its production is excluded from actuals until the
     * reporting date rolls over, so there is nothing yet to score it against.
     */
    public static function isBacktest(Carbon|string $date): bool
    {
        $parsed = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date)->startOfDay();

        return $parsed->lt(ReportingDateService::reportingDate());
    }
}
