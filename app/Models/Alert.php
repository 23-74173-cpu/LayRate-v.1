<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_id', 'alert_type', 'message', 'is_read', 'triggered_at'];

    protected $casts = ['triggered_at' => 'datetime', 'created_at' => 'datetime', 'is_read' => 'boolean'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }
}
