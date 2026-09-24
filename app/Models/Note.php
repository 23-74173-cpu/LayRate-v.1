<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $fillable = ['body', 'cage_id'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }
}
