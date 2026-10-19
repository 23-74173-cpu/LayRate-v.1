<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Relations\BelongsTo;

class EggCountEvent extends Model
{
    protected $fillable = [
        'cage_slot_id',
        'hardware_item_id',
        'production_log_id',
        'user_id',
        'device_id',
        'event_type',
        'old_value',
        'new_value',
        'trigger_count',
        'source',
        'occurred_at',
        'event_key',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'integer',
            'new_value' => 'integer',
            'trigger_count' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function hardwareItem(): BelongsTo
    {
        return $this->belongsTo(HardwareItem::class);
    }

    public function productionLog(): BelongsTo
    {
        return $this->belongsTo(ProductionLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
