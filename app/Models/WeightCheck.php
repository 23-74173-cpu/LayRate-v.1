<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightCheck extends Model
{
    protected $fillable = [
        'hen_id', 'check_date', 'weight_kg', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'check_date' => 'date',
        'weight_kg'  => 'decimal:2',
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
