<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hen extends Model
{
    protected $fillable = ['cage_id', 'tag_code', 'date_acquired', 'flock_age_weeks', 'breed', 'is_active'];

    protected $casts = [
        'date_acquired' => 'date',
        'is_active'     => 'boolean',
    ];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function getFlockAgeLabelAttribute(): string
    {
        $weeks = $this->flock_age_weeks;
        $months = round($weeks / 4.33);
        return "{$weeks} wks ({$months} mo)";
    }
}
