<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Forecast extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_id', 'forecast_date', 'target_date', 'predicted_hdep'];

    protected $casts = [
        'forecast_date' => 'date',
        'target_date'   => 'date',
        'created_at'    => 'datetime',
    ];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function getConfidenceAttribute(): int
    {
        $diff = $this->forecast_date->diffInDays($this->target_date);
        return max(60, 97 - ($diff * 3));
    }

    public function getConfidenceColorAttribute(): string
    {
        $c = $this->confidence;
        if ($c >= 85) return '#D5E8D4';
        if ($c >= 75) return '#FFF3CD';
        return '#F8D7DA';
    }
}
