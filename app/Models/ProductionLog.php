<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_id', 'log_date', 'egg_count', 'hen_count', 'hdep', 'recorded_by', 'notes'];

    protected $casts = ['log_date' => 'date', 'created_at' => 'datetime'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
