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
     * The standard (optimal) range from config/environment.php, with the
     * same defaults as that file in case the config cache predates it.
     */
    public static function optimalRange(): array
    {
        $defaults = ['temp_min' => 18.0, 'temp_max' => 24.0, 'hum_min' => 50.0, 'hum_max' => 70.0];
        $configured = config('environment.optimal');

        return array_map('floatval', array_merge($defaults, is_array($configured) ? $configured : []));
    }

    /**
     * Where a reading sits against a range: 'within' (inclusive), 'above',
     * 'below', or 'none' when there is no reading. 'diff' is how far outside
     * the range it is (0 when within).
     */
    public static function compareToRange(?float $value, float $min, float $max): array
    {
        if ($value === null) {
            return ['state' => 'none', 'diff' => 0.0];
        }

        // Compared at the 1 decimal shown on screen, so a reading displayed
        // as 24.0 °C never reads "above 24.0 °C by 0.0".
        $value = round($value, 1);

        if ($value > $max) {
            return ['state' => 'above', 'diff' => round($value - $max, 1)];
        }

        if ($value < $min) {
            return ['state' => 'below', 'diff' => round($min - $value, 1)];
        }

        return ['state' => 'within', 'diff' => 0.0];
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
