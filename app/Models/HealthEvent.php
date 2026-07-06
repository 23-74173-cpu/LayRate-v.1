<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthEvent extends Model
{
    protected $fillable = [
        'hen_id', 'event_date', 'event_type',
        'description', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function hen(): BelongsTo
    {
        return $this->belongsTo(Hen::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
