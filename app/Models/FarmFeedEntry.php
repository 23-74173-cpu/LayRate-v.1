<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmFeedEntry extends Model
{
    protected $fillable = ['log_date', 'log_time', 'total_kg', 'unit_cost', 'feed_batch_id'];

    protected $casts = [
        'log_date' => 'date',
        'log_time' => 'datetime:H:i',
        'total_kg' => 'float',
        'unit_cost' => 'float',
    ];

    public function feedBatch(): BelongsTo
    {
        return $this->belongsTo(FeedBatch::class);
    }

    public function consumptionLogs(): HasMany
    {
        return $this->hasMany(FeedConsumptionLog::class);
    }
}
