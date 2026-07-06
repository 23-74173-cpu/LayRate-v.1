<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Removal extends Model
{
    protected $fillable = [
        'hen_id', 'removal_date', 'reason',
        'destination', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'removal_date' => 'date',
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
