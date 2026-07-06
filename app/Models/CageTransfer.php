<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CageTransfer extends Model
{
    protected $fillable = [
        'hen_id', 'from_cage_slot_id', 'to_cage_slot_id',
        'transfer_date', 'reason', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function hen(): BelongsTo
    {
        return $this->belongsTo(Hen::class);
    }

    public function fromSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class, 'from_cage_slot_id');
    }

    public function toSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class, 'to_cage_slot_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
