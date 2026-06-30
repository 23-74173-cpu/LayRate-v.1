<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_slot_id', 'log_date', 'egg_count', 'hen_count', 'hdep', 'recorded_by', 'notes', 'overridden_by_user_id', 'overridden_at'];

    protected $casts = ['log_date' => 'date', 'created_at' => 'datetime', 'overridden_at' => 'datetime'];

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by_user_id');
    }
}
