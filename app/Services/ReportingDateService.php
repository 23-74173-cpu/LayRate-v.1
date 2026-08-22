<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

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
        return self::reportingDateFor(Carbon::now(self::timezone()));
    }

    /**
     * The reporting date (start of day, in the farm timezone) for an
     * arbitrary instant — the generalization of reportingDate(). Used by the
     * dedup-key migration to classify existing alerts by when they fired.
     */
    public static function reportingDateFor(CarbonInterface|string $instant): Carbon
    {
        $manila = $instant instanceof CarbonInterface
            ? $instant->copy()->setTimezone(self::timezone())
            // Naive stored values (e.g. alerts.triggered_at) are wall-clock in
            // the app timezone, not the farm timezone.
            : Carbon::parse($instant, config('app.timezone', 'UTC'))->setTimezone(self::timezone());

        $reset = Carbon::parse($manila->format('Y-m-d') . ' ' . self::resetTime(), self::timezone());

        if ($manila->lt($reset)) {
            return $manila->copy()->subDay()->startOfDay();
        }

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

    /**
     * The reporting-day window for a given reporting date, as a half-open
     * [start, end) pair of naive datetime strings in the app timezone.
     *
     * A reporting day runs from the reset time (day_reset_time, default 06:00)
     * on its date until the same time the following day — so an event at 02:00
     * belongs to the *previous* reporting day, exactly matching what
     * reportingDateString() returns at that moment.
     *
     * Used by alert dedup so the "already alerted for this day?" key and the
     * stored `triggered_at` stamp (which is a naive instant in the app
     * timezone via now()) always agree, regardless of clocks.
     */
    public static function reportingDayWindow(string $date): array
    {
        $day = Carbon::parse($date, self::timezone())->toDateString();
        $start = Carbon::parse($day . ' ' . self::resetTime(), self::timezone());
        $end = $start->copy()->addDay();

        $appTz = config('app.timezone', 'UTC');

        return [
            $start->setTimezone($appTz)->toDateTimeString(),
            $end->setTimezone($appTz)->toDateTimeString(),
        ];
    }
}
