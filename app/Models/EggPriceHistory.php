<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EggPriceHistory extends Model
{
    protected $fillable = [
        'egg_size',
        'old_price_per_tray', 'old_price_per_piece',
        'new_price_per_tray', 'new_price_per_piece',
        'changed_by',
    ];

    protected $casts = [
        'old_price_per_tray' => 'decimal:2',
        'old_price_per_piece' => 'decimal:2',
        'new_price_per_tray' => 'decimal:2',
        'new_price_per_piece' => 'decimal:2',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
