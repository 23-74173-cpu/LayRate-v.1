<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastRun extends Model
{
    protected $fillable = [
        'user_id', 'scope', 'cage_id', 'cage_code', 'breed', 'horizon',
        'start_date', 'status', 'error_message', 'result_metrics',
        'redirect_params', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'result_metrics' => 'array',
        'redirect_params' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
