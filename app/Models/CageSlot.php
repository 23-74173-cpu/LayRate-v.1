<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CageSlot extends Model
{
    protected $fillable = [
        'cage_id', 'row_number', 'column_number', 'slot_number',
        'current_occupancy',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'column_number' => 'integer',
        'slot_number' => 'integer',
        'current_occupancy' => 'integer',
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

    public function hardwareItems(): HasMany
    {
        return $this->hasMany(HardwareItem::class);
    }

    public function primaryHen(): ?Hen
    {
        return $this->hens()->where('is_active', 1)->first();
    }

    public function hasBreakbeam(): bool
    {
        if ($this->relationLoaded('hardwareItems')) {
            return $this->hardwareItems
                ->where('device_type', 'IR_breakbeam')
                ->where('status', 'active')
                ->isNotEmpty();
        }
        return $this->hardwareItems()
            ->where('device_type', 'IR_breakbeam')
            ->where('status', 'active')
            ->exists();
    }

    public function getStatusAttribute(): string
    {
        if ($this->current_occupancy === 0) {
            return 'empty';
        }
        if ($this->hasBreakbeam()) {
            return 'sensor';
        }
        return 'manual';
    }

    public function getRemainingAttribute(): int
    {
        return (int) $this->cage->max_chickens_per_slot - (int) $this->current_occupancy;
    }
}
