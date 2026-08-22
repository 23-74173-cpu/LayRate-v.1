<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\EnvironmentalLog;
use App\Models\Setting;

/**
 * Create persistent alerts when environmental readings cross configured thresholds.
 *
 * Boundary convention matches EnvironmentStatusService:
 *   - Alert: value is strictly outside the safe range (< min or > max)
 *   - Watch: value is exactly at a boundary (== min or == max)
 *
 * A single unread alert of each type is created per cage per day to avoid noise.
 */
class EnvironmentAlertService
{
    public static function check(EnvironmentalLog $log): void
    {
        $thresholds = Setting::thresholds();
        $cageId = $log->cage_id;
        $date = $log->recorded_at->toDateString();

        $temp = $log->temperature_c;
        $hum = $log->humidity_pct;

        $tempMin = $thresholds['temp_min'] ?? null;
        $tempMax = $thresholds['temp_max'] ?? null;
        $humMin = $thresholds['hum_min'] ?? null;
        $humMax = $thresholds['hum_max'] ?? null;

        if ($tempMin !== null && $temp < $tempMin) {
            self::createAlert($cageId, 'temperature_low', "Temperature {$temp}°C is below minimum threshold ({$tempMin}°C)", $date);
        }

        if ($tempMax !== null && $temp > $tempMax) {
            self::createAlert($cageId, 'temperature_high', "Temperature {$temp}°C is above maximum threshold ({$tempMax}°C)", $date);
        }

        if ($humMin !== null && $hum < $humMin) {
            self::createAlert($cageId, 'humidity_low', "Humidity {$hum}% is below minimum threshold ({$humMin}%)", $date);
        }

        if ($humMax !== null && $hum > $humMax) {
            self::createAlert($cageId, 'humidity_high', "Humidity {$hum}% is above maximum threshold ({$humMax}%)", $date);
        }
    }

    private static function createAlert(?int $cageId, string $type, string $message, string $date): void
    {
        $exists = Alert::where('cage_id', $cageId)
            ->where('alert_type', $type)
            ->where('is_read', 0)
            ->whereDate('triggered_at', $date)
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
