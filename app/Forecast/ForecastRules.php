<?php

namespace App\Forecast;

use Carbon\Carbon;

class ForecastRules
{
    /**
     * The earliest allowed start date for a forecast (strictly after today).
     * Both Laravel validation and frontend calendar use this as their single
     * source of truth.
     */
    public static function minStartDate(): Carbon
    {
        return now()->addDay()->startOfDay();
    }

    /**
     * The latest allowed start date (30 days from today).
     */
    public static function maxStartDate(): Carbon
    {
        return now()->addDays(30)->endOfDay();
    }
}
