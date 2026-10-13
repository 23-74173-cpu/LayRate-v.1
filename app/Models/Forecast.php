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
        'forecast_key',
        'is_backtest',
        'superseded_at',
        'invalidated_at',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'target_date'   => 'date',
        'created_at'    => 'datetime',
        'is_backtest'   => 'boolean',
        'superseded_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    /**
     * The prediction as it currently stands: the newest one for this
     * (cage, breed, target_date) that has not been superseded by a later run.
     */
    public function scopeLive($query)
    {
        return $query->whereNull('superseded_at');
    }

    /** Only predictions issued before their target day qualify for comparison. */
    public function scopeIssuedInAdvance($query)
    {
        return $query->whereNull('invalidated_at')->where('is_backtest', false)
            ->whereColumn('forecast_date', '<', 'target_date');
    }

    public static function fixedKey(?int $cageId, ?string $breed, string $date): string
    {
        return hash('sha256', json_encode([$cageId, $breed, substr($date, 0, 10)]));
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

}
