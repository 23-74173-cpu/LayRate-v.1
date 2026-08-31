<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ReportingDateService
{
    /**
     * The farm timezone is hardcoded to Asia/Manila.
     */
    public static function timezone(): string
    {
        return 'Asia/Manila';
    }

    /**
     * Current time in the configured timezone.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /**
     * The current reporting date — midnight is the day boundary.
     */
    public static function reportingDate(): Carbon
    {
        return self::reportingDateFor(Carbon::now(self::timezone()));
    }

    /**
     * The reporting date for an arbitrary instant — midnight boundary.
     */
    public static function reportingDateFor(CarbonInterface|string $instant): Carbon
    {
        $manila = $instant instanceof CarbonInterface
            ? $instant->copy()->setTimezone(self::timezone())
            : Carbon::parse($instant, config('app.timezone', 'UTC'))->setTimezone(self::timezone());

        return $manila->copy()->startOfDay();
    }

    /**
     * The current reporting date as a date string (Y-m-d).
     */
    public static function reportingDateString(): string
    {
        return self::reportingDate()->toDateString();
    }

    /**
     * Start of the current reporting day (midnight).
     */
    public static function reportingDayStart(): Carbon
    {
        return self::reportingDate()->copy()->startOfDay();
    }

    /**
     * End of the current reporting day (exclusive — 23:59:59.999999).
     */
    public static function reportingDayEnd(): Carbon
    {
        return self::reportingDayStart()->copy()->endOfDay();
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

    /**
     * The reporting-day window for a given reporting date, as a half-open
     * [start, end) pair of naive datetime strings in the app timezone.
     *
     * With midnight boundary: the day runs from 00:00 to 23:59:59.999999.
     */
    public static function reportingDayWindow(string $date): array
    {
        $day = Carbon::parse($date, self::timezone())->toDateString();
        $start = Carbon::parse($day . ' 00:00:00', self::timezone());
        $end = $start->copy()->endOfDay();

        $appTz = config('app.timezone', 'UTC');

        return [
            $start->setTimezone($appTz)->toDateTimeString(),
            $end->setTimezone($appTz)->toDateTimeString(),
        ];
    }
}
