<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreOrder extends Model
{
    protected $fillable = [
        'customer_name',
        'customer_reference',
        'egg_size',
        'egg_count',
        'requested_date',
        'fulfillment_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'egg_count' => 'integer',
        'requested_date' => 'date',
        'fulfillment_date' => 'date',
    ];

    public function getTrayCountAttribute(): int
    {
        return (int) ceil($this->egg_count / 30);
    }
}
