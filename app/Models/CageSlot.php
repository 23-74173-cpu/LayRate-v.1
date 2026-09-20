<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * Sensors covering this slot via the multi-slot pivot (in addition to
     * primary hardwareItems above).
     */
    public function additionalSensors(): BelongsToMany
    {
        return $this->belongsToMany(HardwareItem::class, 'hardware_item_cage_slot');
    }

    public function primaryHen(): ?Hen
    {
        return $this->hens()->where('is_active', 1)->first();
    }

    public function getActiveHenCountAttribute(): int
    {
        if ($this->relationLoaded('hens')) {
            return $this->hens->where('is_active', 1)->count();
        }
        return $this->hens()->where('is_active', 1)->count();
    }

    public function hasBreakbeam(): bool
    {
        if ($this->relationLoaded('hardwareItems')) {
            $primary = $this->hardwareItems
                ->where('device_type', 'IR_breakbeam')
                ->where('status', 'active')
                ->isNotEmpty();
            if ($primary) {
                return true;
            }
        } elseif ($this->hardwareItems()
            ->where('device_type', 'IR_breakbeam')
            ->where('status', 'active')
            ->exists()) {
            return true;
        }
        return $this->additionalSensors()
            ->where('device_type', 'IR_breakbeam')
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Serial of the first active IR sensor covering this slot (primary
     * assignment first, then multi-slot extras) — for slot JSON/UI labels.
     */
    public function breakbeamSerial(): string
    {
        $primary = $this->relationLoaded('hardwareItems')
            ? $this->hardwareItems->where('device_type', 'IR_breakbeam')->where('status', 'active')->first()
            : $this->hardwareItems()->where('device_type', 'IR_breakbeam')->where('status', 'active')->first();
        if ($primary) {
            return $primary->serial_number;
        }
        return $this->additionalSensors()->where('device_type', 'IR_breakbeam')->where('status', 'active')->first()?->serial_number ?? '';
    }

    public function getStatusAttribute(): string
    {
        if ($this->hasBreakbeam()) {
            return 'sensor';
        }
        if ($this->current_occupancy === 0) {
            return 'empty';
        }
        return 'manual';
    }

    public function getRemainingAttribute(): int
    {
        return (int) $this->cage->max_chickens_per_slot - (int) $this->current_occupancy;
    }
}
