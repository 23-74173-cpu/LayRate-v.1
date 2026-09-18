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
     * @return \Illuminate\Support\Collection<int, static|null>
     */
    public static function latestRealPerCage(iterable $cageIds): \Illuminate\Support\Collection
    {
        return static::whereIn('cage_id', $cageIds)
            ->real()
            ->orderByDesc('recorded_at')
            ->get()
            ->groupBy('cage_id')
            ->map(fn ($group) => $group->first());
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
