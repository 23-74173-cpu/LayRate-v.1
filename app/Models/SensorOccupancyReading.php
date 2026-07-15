<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorOccupancyReading extends Model
{
    protected $fillable = [
        'hardware_item_id',
        'cage_slot_id',
        'reported_count',
        'recorded_at',
    ];

    protected $casts = [
        'reported_count' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function hardwareItem(): BelongsTo
    {
        return $this->belongsTo(HardwareItem::class);
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }
}
