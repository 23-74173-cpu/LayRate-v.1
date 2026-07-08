<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HardwareItem extends Model
{
    protected $fillable = [
        'device_type',
        'serial_number',
        'cage_id',
        'cage_slot_id',
        'installation_date',
        'status',
        'last_calibration_date',
    ];

    protected function casts(): array
    {
        return [
            'installation_date'     => 'date',
            'last_calibration_date' => 'date',
        ];
    }

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
