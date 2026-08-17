<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'label'];

    // Config values like these change rarely (admin-edited thresholds, egg
    // weights, farm grid size) but were being re-queried on every single
    // request that touched them — e.g. every sensor-ingestion reading reads
    // thresholds() to evaluate alerts, every dashboard/report load reads
    // get('feed_per_hen_daily', ...). 1 hour is generous relative to how
    // often these actually change (an admin editing a threshold) — cache
    // writes below invalidate immediately on any set() anyway, so this TTL
    // only matters as a fallback if invalidation is ever bypassed (e.g. a
    // direct DB write outside this model).
    private const CACHE_TTL = 3600;

    public static function get(string $key, mixed $default = null): mixed
    {
        // Caches only the raw stored value (or null), never the
        // default-substituted result: get() is called with genuinely
        // different defaults for the same key at different call sites
        // (e.g. 'farm_grid_rows' defaults to 6 in one controller and 4 in
        // another). Caching the post-default value would let whichever
        // call site runs first silently poison the cache for the other.
        $value = Cache::remember(
            self::cacheKey($key),
            self::CACHE_TTL,
            fn () => static::where('key', $key)->value('value')
        );

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        // Settings are written rarely (admin-only) and this model has no way
        // to know from a single key whether it's one of the keys thresholds()
        // /eggWeights() bundle together, so every write clears all of this
        // model's cache entries rather than trying to track which blob a key
        // belongs to. Correct and simple; the coarseness costs nothing given
        // how infrequently set() is actually called.
        Cache::forget(self::cacheKey($key));
        Cache::forget('settings:thresholds');
        Cache::forget('settings:egg_weights');
    }

    public static function thresholds(): array
    {
        return Cache::remember('settings:thresholds', self::CACHE_TTL, function () {
            $rows = static::whereIn('key', ['temp_min', 'temp_max', 'hum_min', 'hum_max'])->pluck('value', 'key');

            return [
                'temp_min' => (float) ($rows['temp_min'] ?? 18),
                'temp_max' => (float) ($rows['temp_max'] ?? 30),
                'hum_min'  => (float) ($rows['hum_min']  ?? 40),
                'hum_max'  => (float) ($rows['hum_max']  ?? 70),
            ];
        });
    }

    public static function eggWeights(): array
    {
        return Cache::remember('settings:egg_weights', self::CACHE_TTL, function () {
            $rows = static::whereIn('key', [
                'egg_weight_small',
                'egg_weight_medium',
                'egg_weight_large',
                'egg_weight_jumbo',
                'egg_weight_fallback',
            ])->pluck('value', 'key');

            return [
                'small'     => (float) ($rows['egg_weight_small']     ?? 50),
                'medium'    => (float) ($rows['egg_weight_medium']    ?? 58),
                'large'     => (float) ($rows['egg_weight_large']     ?? 65),
                'jumbo'     => (float) ($rows['egg_weight_jumbo']     ?? 73),
                'fallback'  => (float) ($rows['egg_weight_fallback']  ?? 60),
            ];
        });
    }

    private static function cacheKey(string $key): string
    {
        return "setting:{$key}";
    }
}
