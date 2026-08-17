<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HardwareItem extends Model
{
    protected $fillable = [
        'device_type',
        'serial_number',
        'cage_id',
        'cage_slot_id',
        'device_id',
        'installation_date',
        'status',
        'last_calibration_date',
        'relay_status',
        'control_mode',
        'relay_safety',
        'last_changed_at',
        'last_changed_by',
        'relay_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'last_calibration_date' => 'date',
            'relay_safety' => 'boolean',
            'last_changed_at' => 'datetime',
            'relay_seen_at' => 'datetime',
        ];
    }

    const CONTROL_MODES = ['auto', 'manual'];

    const DEVICE_TYPES = ['DHT22', 'IR_breakbeam', 'relay', 'other'];

    const STATUSES = ['active', 'faulty', 'removed', 'spare'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function lastChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_changed_by');
    }

    public function latestOccupancyReading(): HasOne
    {
        return $this->hasOne(SensorOccupancyReading::class)->latestOfMany('recorded_at');
    }

    /**
     * Sensors that can be assigned from inventory:
     * explicitly spare, or active-but-currently-unassigned.
     */
    public function scopeAvailableForAssignment($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'spare')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'active')
                     ->whereNull('cage_id')
                     ->whereNull('cage_slot_id');
              });
        });
    }
}
