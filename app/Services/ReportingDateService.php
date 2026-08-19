<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;

class ReportingDateService
{
    /**
     * The farm timezone is hardcoded to Asia/Manila.
     *
     * The application relies on the Pi's system clock/timezone; this method
     * only ensures every reporting calculation uses the same zone.
     */
    public static function timezone(): string
    {
        return 'Asia/Manila';
    }

    /**
     * Get the configured day reset time as "H:i" (default 06:00).
     */
    public static function resetTime(): string
    {
        $time = Setting::get('day_reset_time', '06:00');
        if (! $time || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return '06:00';
        }

        return $time;
    }

    /**
     * Current time in the configured timezone.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /**
     * The current reporting date based on the reset time.
     *
     * If the current time is before the reset time, the reporting date is
     * still the previous calendar day.
     */
    public static function reportingDate(): Carbon
    {
        $now = self::now();
        $reset = Carbon::parse($now->format('Y-m-d') . ' ' . self::resetTime(), self::timezone());

        if ($now->lt($reset)) {
            return $now->copy()->subDay()->startOfDay();
        }

        return $now->copy()->startOfDay();
    }

    /**
     * The current reporting date as a date string (Y-m-d).
     */
    public static function reportingDateString(): string
    {
        return self::reportingDate()->toDateString();
    }

    /**
     * Start of the current reporting day (inclusive).
     */
    public static function reportingDayStart(): Carbon
    {
        $date = self::reportingDate();
        $reset = Carbon::parse($date->format('Y-m-d') . ' ' . self::resetTime(), self::timezone());

        return $reset;
    }

    /**
     * End of the current reporting day (exclusive).
     */
    public static function reportingDayEnd(): Carbon
    {
        return self::reportingDayStart()->copy()->addDay();
    }

    /**
     * Convert a date string in the configured timezone to the start of that day.
     */
    public static function dateStart(string $date): Carbon
    {
        return Carbon::parse($date . ' 00:00:00', self::timezone())->startOfDay();
    }

    /**
     * Convert a date string in the configured timezone to the end of that day.
     */
    public static function dateEnd(string $date): Carbon
    {
        return Carbon::parse($date . ' 23:59:59', self::timezone())->endOfDay();
    }
}
