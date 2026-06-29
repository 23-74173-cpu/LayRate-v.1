<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CageSlot extends Model
{
    protected $fillable = [
        'cage_id', 'row_number', 'column_number', 'slot_number',
        'current_occupancy', 'has_sensor', 'sensor_device_id',
    ];

    protected $casts = ['has_sensor' => 'boolean'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function hens(): HasMany
    {
        return $this->hasMany(Hen::class);
    }

    public function productionLogs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    public function latestProduction()
    {
        return $this->hasOne(ProductionLog::class)->latestOfMany('log_date');
    }

    public function getPrimaryHenAttribute(): ?Hen
    {
        return $this->hens()->where('is_active', 1)->first();
    }

    public function getStatusAttribute(): string
    {
        if ($this->current_occupancy <= 0) {
            return 'empty';
        }

        if ($this->current_occupancy >= $this->cage->max_chickens_per_slot) {
            return 'full';
        }

        return 'partial';
    }

    public function getLabelAttribute(): string
    {
        $rowLetter = chr(64 + $this->row_number); // 1 -> A, 2 -> B, 3 -> C ...
        return "{$rowLetter}-" . str_pad($this->column_number, 2, '0', STR_PAD_LEFT);
    }
}
