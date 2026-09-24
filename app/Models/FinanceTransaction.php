<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    // recorded_by is set by the controller from the signed-in user, never
    // from request input.
    protected $fillable = ['type', 'category', 'amount', 'description', 'date', 'cage_id'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public const TYPES = ['income', 'expense'];

    // Starting categories, still to be confirmed with the farm. Edit these
    // lists to add or rename one; the column is a plain string, so no
    // migration is needed.
    public const CATEGORIES = [
        'income'  => ['Egg Sales', 'Hen Sales', 'Other Income'],
        'expense' => ['Feed Cost', 'Medicine', 'Labor', 'Utilities', 'Equipment & Repairs', 'Other Expense'],
    ];

    public static function categoriesFor(?string $type): array
    {
        return self::CATEGORIES[$type] ?? [];
    }

    public static function allCategories(): array
    {
        return array_merge(...array_values(self::CATEGORIES));
    }

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isIncome(): bool
    {
        return $this->type === 'income';
    }
}
