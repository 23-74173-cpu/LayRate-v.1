<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedConsumptionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cage_id',
        'feed_batch_id',
        'log_date',
        'log_time',
        'feed_consumed_kg',
        'recorded_by',
        'source',
        'farm_feed_entry_id',
    ];

    protected $casts = [
        'log_date' => 'date',
        'log_time' => 'datetime:H:i',
        'created_at' => 'datetime',
        'feed_consumed_kg' => 'float',
    ];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function feedBatch(): BelongsTo
    {
        return $this->belongsTo(FeedBatch::class);
    }

    public function farmFeedEntry(): BelongsTo
    {
        return $this->belongsTo(FarmFeedEntry::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isDistributed(): bool
    {
        return $this->source === 'distributed';
    }
}
