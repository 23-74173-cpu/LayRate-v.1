<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    protected $fillable = ['type', 'category', 'amount', 'description', 'date', 'cage_id', 'recorded_by'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public const TYPES = ['income', 'expense'];

    // Provisional — categories to be confirmed with stakeholders. Shared
    // across both income and expense rather than split into two lists.
    public const CATEGORIES = ['Feed Cost', 'Medicine', 'Labor', 'Egg Sales', 'Hen Sales'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
