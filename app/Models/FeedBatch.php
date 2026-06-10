<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedBatch extends Model
{
    protected $fillable = ['batch_code', 'crude_protein', 'date_received', 'notes'];

    protected $casts = ['date_received' => 'date'];

    public function consumptionLogs(): HasMany
    {
        return $this->hasMany(FeedConsumptionLog::class);
    }

    public function getCpColorAttribute(): string
    {
        if ($this->crude_protein >= 17.5) return '#D5E8D4';
        if ($this->crude_protein >= 16.5) return '#FFF3CD';
        return '#F8D7DA';
    }

    public function getCpTextAttribute(): string
    {
        if ($this->crude_protein >= 17.5) return '#2D6A4F';
        if ($this->crude_protein >= 16.5) return '#856404';
        return '#721C24';
    }
}
