<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EggStockBatch extends Model
{
    protected $fillable = [
        'egg_size',
        'count',
        'harvested_date',
        'cage_id',
        'cage_slot_id',
        'source_production_log_id',
    ];

    protected $casts = [
        'count' => 'integer',
        'harvested_date' => 'date',
    ];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function sourceProductionLog(): BelongsTo
    {
        return $this->belongsTo(ProductionLog::class, 'source_production_log_id');
    }

    public function getFreshnessStatusAttribute(): string
    {
        $days = (int) now()->diffInDays($this->harvested_date, false);

        if ($days < 0) {
            return 'fresh';
        }

        if ($days <= 7) {
            return 'fresh';
        }

        if ($days <= 14) {
            return 'aging';
        }

        return 'old';
    }
}
