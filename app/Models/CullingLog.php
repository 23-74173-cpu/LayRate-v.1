<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CullingLog extends Model
{
    protected $fillable = [
        'hen_id', 'cull_date', 'reason', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'cull_date' => 'date',
    ];

    const REASONS = ['low_production', 'illness', 'aggression', 'age', 'other'];

    public function hen(): BelongsTo
    {
        return $this->belongsTo(Hen::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
