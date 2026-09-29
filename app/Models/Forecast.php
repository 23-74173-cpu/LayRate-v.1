<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Forecast extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cage_id',
        'cage_slot_id',
        'breed',
        'forecast_date',
        'target_date',
        'predicted_egg_count',
        'forecast_run_id',
        'is_backtest',
        'superseded_at',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'target_date'   => 'date',
        'created_at'    => 'datetime',
        'is_backtest'   => 'boolean',
        'superseded_at' => 'datetime',
    ];

    /**
     * The prediction as it currently stands: the newest one for this
     * (cage, breed, target_date) that has not been superseded by a later run.
     */
    public function scopeLive($query)
    {
        return $query->whereNull('superseded_at');
    }

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class, 'forecast_run_id');
    }

    public function getConfidenceAttribute(): int
    {
        $diff = $this->forecast_date->diffInDays($this->target_date);
        return max(60, 97 - ($diff * 3));
    }

    public function getConfidenceColorAttribute(): string
    {
        $c = $this->confidence;
        if ($c >= 85) return '#D5E8D4';
        if ($c >= 75) return '#FFF3CD';
        return '#F8D7DA';
    }
}
