<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentalLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_id', 'recorded_at', 'temperature_c', 'humidity_pct', 'is_override', 'is_demo'];

    protected $casts = ['recorded_at' => 'datetime', 'created_at' => 'datetime', 'is_override' => 'boolean', 'is_demo' => 'boolean'];

    /**
     * Quarantine scope — DemoDataSeeder rows (is_demo = 1) are kept in the
     * table but excluded from forecasting and live-monitoring reads so the
     * Mar-Jun 2026 device-originated import is the sole source.
     */
    public function scopeReal($query)
    {
        return $query->where('is_demo', false);
    }

    /**
     * Latest real (non-demo) reading per cage, keyed by cage_id.
     *
     * NOTE: implemented as an explicit ordered query, NOT as a constrained
     * latestOfMany relationship — latestOfMany silently returns null for
     * every parent once an extra where() is added (verified on Laravel
     * 12.64: both closure-constrained eager loads and in-relationship
     * wheres misfire). Callers set the result onto the model with
     * setRelation('latestEnvironmentLog', ...) so downstream reads stay
     * unchanged.
     *
     * One small query per cage, each reading a single row through the
     * (cage_id, recorded_at) unique index. The old version loaded every
     * reading of every cage into PHP to keep only the newest one: with a
     * DHT22 reading every 2 seconds that is ~43,000 rows per sensor per day,
     * which made the Dashboard, Environment, and Hardware pages slower every
     * day and eventually ran PHP out of memory.
     *
     * Only cages that have a reading are in the result (same as before).
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function latestRealPerCage(iterable $cageIds): \Illuminate\Support\Collection
    {
        return collect($cageIds)
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($cageId) => [
                $cageId => static::where('cage_id', $cageId)
                    ->real()
                    ->orderByDesc('recorded_at')
                    ->first(),
            ])
            ->filter();
    }

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function getTempStatusAttribute(): string
    {
        if ($this->temperature_c > 30) return 'Alert';
        if ($this->temperature_c > 28.5) return 'Watch';
        return 'OK';
    }

    public function getHumStatusAttribute(): string
    {
        if ($this->humidity_pct > 70) return 'Alert';
        if ($this->humidity_pct >= 70) return 'Watch';
        return 'OK';
    }
}
