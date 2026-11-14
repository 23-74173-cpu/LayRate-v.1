<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\EnvironmentalLog;
use App\Models\Setting;

/**
 * Create persistent alerts when environmental readings rise above the
 * configured maximum thresholds. There are no minimum thresholds; existing
 * temperature_low/humidity_low alerts stay in history but are no longer raised.
 *
 * Boundary convention matches EnvironmentStatusService: alert only when the
 * value is strictly above the maximum.
 *
 * A single unread alert of each type is created per cage per day to avoid noise.
 */
class EnvironmentAlertService
{
    public static function check(EnvironmentalLog $log): void
    {
        $thresholds = Setting::thresholds();
        $cageId = $log->cage_id;

        $temp = $log->temperature_c;
        $hum = $log->humidity_pct;

        $tempMax = $thresholds['temp_max'] ?? null;
        $humMax = $thresholds['hum_max'] ?? null;

        if ($tempMax !== null && $temp > $tempMax) {
            self::createAlert($cageId, 'temperature_high', "Temperature {$temp}°C is above maximum threshold ({$tempMax}°C)");
        }

        if ($humMax !== null && $hum > $humMax) {
            self::createAlert($cageId, 'humidity_high', "Humidity {$hum}% is above maximum threshold ({$humMax}%)");
        }
    }

    private static function createAlert(?int $cageId, string $type, string $message): void
    {
        [$dayStart, $dayEnd] = ReportingDateService::reportingDayWindow(ReportingDateService::reportingDateString());

        $exists = Alert::where('cage_id', $cageId)
            ->where('alert_type', $type)
            ->where('is_read', 0)
            ->where('triggered_at', '>=', $dayStart)
            ->where('triggered_at', '<', $dayEnd)
            ->exists();

        if ($exists) {
            return;
        }

        Alert::createDeduped([
            'cage_id' => $cageId,
            'alert_type' => $type,
            'message' => $message,
            'is_read' => 0,
            'triggered_at' => now(),
            'dedup_key' => Alert::dedupKey($cageId, $type),
            'alert_day' => ReportingDateService::reportingDateString(),
        ]);
    }
}
