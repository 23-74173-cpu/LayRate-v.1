<?php

namespace App\Services;

/**
 * Source-key prefixes of imported cage/day aggregate history.
 *
 * Keys look like "<prefix>:<Y-m-d>:<CAGE-CODE>:<kind>". production_data_clean
 * holds the original supplied CSV rows; simulated_extension holds the
 * simulated Aug 28+ extension, which is shown (labelled) in history views but
 * never used as forecast training data or evaluation ground truth.
 */
class HistoricalSource
{
    public const ORIGINAL = 'production_data_clean';

    public const SIMULATED = 'simulated_extension';

    /**
     * Constrain $query to imported rows of $kind (production, feed,
     * environment, mortality) from either source. Use inside where()/orWhere()
     * closures so the OR stays grouped.
     */
    public static function match($query, string $column, string $kind)
    {
        return $query->where($column, 'like', self::ORIGINAL.':%:'.$kind)
            ->orWhere($column, 'like', self::SIMULATED.':%:'.$kind);
    }

    public static function isSimulated(?string $sourceKey): bool
    {
        return str_starts_with((string) $sourceKey, self::SIMULATED.':');
    }
}
