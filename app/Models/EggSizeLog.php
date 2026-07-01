<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EggSizeLog extends Model
{
    protected $fillable = ['production_log_id', 'egg_size', 'count'];

    protected $casts = ['count' => 'integer'];

    public function productionLog(): BelongsTo
    {
        return $this->belongsTo(ProductionLog::class);
    }
}
