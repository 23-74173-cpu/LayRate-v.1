<?php

namespace App\Services;

/**
 * FCR status classification using configurable thresholds.
 *
 * Thresholds are adjustable by editing the egg weight configuration
 * in the egg stocks section. Expected FCR varies by breed, flock age,
 * and whether birds are in lay, pre-lay, or molting.
 */
class FcrStatusService
{
    const GOOD = 'good';
    const WARNING = 'warning';
    const CRITICAL = 'critical';
    const NA = 'na';

    public static function status(?float $fcr): string
    {
        if ($fcr === null) {
            return self::NA;
        }

        $goodThreshold = (float) config('fcr.good_threshold', 2.5);
        $warningThreshold = (float) config('fcr.warning_threshold', 4.0);

        if ($fcr <= $goodThreshold) {
            return self::GOOD;
        }

        if ($fcr <= $warningThreshold) {
            return self::WARNING;
        }

        return self::CRITICAL;
    }

    public static function label(?float $fcr): string
    {
        return match (self::status($fcr)) {
            self::GOOD     => 'Good',
            self::WARNING  => 'Warning',
            self::CRITICAL => 'Critical',
            self::NA       => 'N/A',
        };
    }

    /**
     * Tailwind CSS classes for the status badge based on status.
     */
    public static function badgeClasses(?float $fcr): array
    {
        return match (self::status($fcr)) {
            self::GOOD => [
                'bg'    => 'bg-green-100',
                'text'  => 'text-green-800',
                'dot'   => 'bg-green-500',
                'icon'  => 'check-circle',
            ],
            self::WARNING => [
                'bg'    => 'bg-yellow-100',
                'text'  => 'text-yellow-800',
                'dot'   => 'bg-yellow-500',
                'icon'  => 'alert-triangle',
            ],
            self::CRITICAL => [
                'bg'    => 'bg-red-100',
                'text'  => 'text-red-800',
                'dot'   => 'bg-red-500',
                'icon'  => 'alert-octagon',
            ],
            self::NA => [
                'bg'    => 'bg-gray-100',
                'text'  => 'text-gray-500',
                'dot'   => 'bg-gray-400',
                'icon'  => 'help-circle',
            ],
        };
    }
}
