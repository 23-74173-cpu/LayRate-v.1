<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentalLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_id', 'recorded_at', 'temperature_c', 'humidity_pct'];

    protected $casts = ['recorded_at' => 'datetime', 'created_at' => 'datetime'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function getTempStatusAttribute(): string
    {
        if ($this->temperature_c > 30) return 'Alert';
        if ($this->temperature_c > 28.5) return 'Watch';
        return 'OK';
    }

    public function getHumStatusAttribute(): string
    {
        if ($this->humidity_pct > 70) return 'Alert';
        if ($this->humidity_pct >= 70) return 'Watch';
        return 'OK';
    }
}
