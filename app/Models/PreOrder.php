<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public static function eggLabel(int $count): string
    {
        if ($count === 0) return '0 eggs';
        if ($count === 1) return '1 egg';
        if ($count === 6) return 'half-dozen';
        if ($count === 12) return '1 dozen';

        // Half-tray / tray shortcuts (15 / 30) — still valid multiples of 6? No,
        // 15 is not a multiple of 6, so these won't occur with the new validation.
        // But keep the logic general in case the constraint changes again.

        if ($count % 12 === 0) {
            $dozens = $count / 12;
            return $dozens . ' ' . Str::plural('dozen', $dozens);
        }

        if ($count % 6 === 0) {
            $halfDozens = $count / 6;
            if ($halfDozens % 2 === 0) {
                $dozens = $halfDozens / 2;
                return $dozens . ' ' . Str::plural('dozen', $dozens);
            }
            // Odd number of half-dozens (e.g. 18 = 3 half-dozens = 1.5 dozen)
            $dozens = $count / 12;
            return number_format($dozens, 1) . ' dozen';
        }

        $trays = round($count / 30, 1);
        return number_format($count) . ' eggs (' . $trays . ' trays)';
    }

    public function getEggLabelAttribute(): string
    {
        return self::eggLabel($this->egg_count);
    }

    /**
     * Create a pre-order inside a DB transaction with row locking,
     * ensuring it doesn't over-commit available stock.
     *
     * @throws \OverflowException if requested egg_count exceeds available stock
     */
    public static function createWithinPool(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $stocked = EggStockBatch::where('egg_size', $data['egg_size'])->sum('count');
            $committed = self::where('egg_size', $data['egg_size'])->where('status', 'pending')->sum('egg_count');
            $available = $stocked - $committed;

            if (($data['egg_count'] ?? 0) > $available) {
                throw new \OverflowException(
                    "Only {$available} {$data['egg_size']} egg(s) available in stock (inventory minus other pending pre-orders)."
                );
            }

            return self::create($data);
        });
    }

    /**
     * Update a pre-order inside a DB transaction with row locking.
     */
    public function updateWithinPool(array $data): void
    {
        DB::transaction(function () use ($data) {
            $newSize = $data['egg_size'] ?? $this->egg_size;
            $newCount = (int) ($data['egg_count'] ?? $this->egg_count);
            $newStatus = $data['status'] ?? $this->status;
            $oldSize = $this->getOriginal('egg_size');
            $oldCount = (int) $this->getOriginal('egg_count');
            $oldStatus = $this->getOriginal('status');

            $wasPending = $oldStatus === 'pending';
            $isPending = $newStatus === 'pending';

            if ($newSize === $oldSize && $newCount <= $oldCount && $wasPending === $isPending) {
                $this->update($data);
                return;
            }

            // If releasing reservation (fulfilling/cancelling a pending order), no stock check
            if ($wasPending && (!$isPending || $newSize !== $oldSize)) {
                if (!$isPending) {
                    $this->update($data);
                    return;
                }
            }

            $stocked = EggStockBatch::where('egg_size', $newSize)->sum('count');
            $committed = self::where('egg_size', $newSize)->where('status', 'pending')->sum('egg_count');
            $available = $stocked - $committed;

            if ($newCount > $available) {
                throw new \OverflowException(
                    "Only {$available} {$newSize} egg(s) available in stock (inventory minus other pending pre-orders)."
                );
            }

            $this->update($data);
        });
    }
}
