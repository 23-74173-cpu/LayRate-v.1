<?php

namespace App\Services;

/**
 * Determine environmental status badges for temperature and humidity
 * using configurable min/max thresholds.
 *
 * Boundary convention (applied identically to temperature and humidity):
 *   - Alert: value is strictly outside the safe range (< min or > max)
 *   - Watch: value is exactly at a boundary (== min or == max)
 *   - OK:    value is strictly inside the safe range (min < value < max)
 */
class EnvironmentStatusService
{
    public static function tempStatus(float $temp, array $thresholds): string
    {
        $min = $thresholds['temp_min'];
        $max = $thresholds['temp_max'];

        if ($temp < $min || $temp > $max) {
            return 'Alert';
        }

        if ($temp <= $min || $temp >= $max) {
            return 'Watch';
        }

        return 'OK';
    }

    public static function humStatus(float $hum, array $thresholds): string
    {
        $min = $thresholds['hum_min'];
        $max = $thresholds['hum_max'];

        if ($hum < $min || $hum > $max) {
            return 'Alert';
        }

        if ($hum <= $min || $hum >= $max) {
            return 'Watch';
        }

        return 'OK';
    }

    /**
     * Overall cage status combining temperature and humidity.
     */
    public static function summary(float $temp, float $hum, array $thresholds): string
    {
        $tempStatus = self::tempStatus($temp, $thresholds);
        $humStatus = self::humStatus($hum, $thresholds);

        if ($tempStatus === 'Alert' || $humStatus === 'Alert') {
            return 'Alert';
        }

        if ($tempStatus === 'Watch' || $humStatus === 'Watch') {
            return 'Watch';
        }

        return 'Normal';
    }
}
