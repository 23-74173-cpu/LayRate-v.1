<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MortalityLogHen extends Model
{
    protected $table = 'mortality_log_hens';

    protected $fillable = ['mortality_log_id', 'hen_id', 'cage_slot_id'];

    public function mortalityLog(): BelongsTo
    {
        return $this->belongsTo(MortalityLog::class);
    }

    public function hen(): BelongsTo
    {
        return $this->belongsTo(Hen::class);
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }
}
