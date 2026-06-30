<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hen extends Model
{
    protected $fillable = [
        'cage_slot_id', 'tag_code', 'date_acquired', 'flock_age_weeks',
        'placement_date', 'age_at_placement_weeks', 'breed', 'is_active',
    ];

    protected $casts = [
        'date_acquired'  => 'date',
        'placement_date' => 'date',
        'is_active'      => 'boolean',
    ];

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function getCurrentAgeWeeksAttribute(): int
    {
        if ($this->placement_date !== null) {
            $weeksElapsed = (int) floor($this->placement_date->diffInWeeks(now()));
            return (int) $this->age_at_placement_weeks + $weeksElapsed;
        }

        return $this->flock_age_weeks;
    }
}
