<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EggPrice extends Model
{
    protected $fillable = ['egg_size', 'price_per_tray', 'price_per_piece', 'updated_by'];

    protected $casts = [
        'price_per_tray' => 'decimal:2',
        'price_per_piece' => 'decimal:2',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Prices keyed by size code, in EggSize display order. */
    public static function keyedBySize(): array
    {
        return self::all()->keyBy('egg_size')->all();
    }
}
