<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedConsumptionLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_id', 'feed_batch_id', 'log_date', 'feed_consumed_kg', 'recorded_by'];

    protected $casts = ['log_date' => 'date', 'created_at' => 'datetime'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function feedBatch(): BelongsTo
    {
        return $this->belongsTo(FeedBatch::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
