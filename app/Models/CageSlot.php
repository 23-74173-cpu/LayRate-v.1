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

    protected $casts = [
        'row_number' => 'integer',
        'column_number' => 'integer',
        'slot_number' => 'integer',
        'current_occupancy' => 'integer',
        'has_sensor' => 'boolean',
    ];

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

    public function primaryHen(): ?Hen
    {
        return $this->hens()->where('is_active', 1)->first();
    }

    public function getStatusAttribute(): string
    {
        if ($this->current_occupancy === 0) {
            return 'empty';
        }
        if ($this->has_sensor) {
            return 'sensor';
        }
        return 'manual';
    }
}
