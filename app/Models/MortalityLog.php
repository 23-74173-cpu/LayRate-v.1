<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MortalityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_id', 'log_date', 'count', 'reason', 'notes', 'recorded_by'];

    protected $casts = ['log_date' => 'date', 'created_at' => 'datetime'];

    const REASONS = ['Disease', 'Heat Stress', 'Injury', 'Predator', 'Unknown', 'Other'];

    public function cage()
    {
        return $this->belongsTo(Cage::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
