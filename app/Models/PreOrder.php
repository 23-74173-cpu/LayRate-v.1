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
     * Compute available pool for a size with row-level locking.
     *
     * Formula: SUM(egg_size_logs.count) - SUM(egg_stock_batches.count) - SUM(pending pre_orders.egg_count)
     * This matches EggStockBatch::getAvailablePoolForSize farm-wide (cageId = null).
     */
    private static function getPoolWithLock(string $size): int
    {
        EggSizeLog::where('egg_size', $size)->lockForUpdate()->get();
        EggStockBatch::where('egg_size', $size)->lockForUpdate()->get();
        self::where('egg_size', $size)->where('status', 'pending')->lockForUpdate()->get();

        $logged = EggSizeLog::where('egg_size', $size)->sum('count');
        $stocked = EggStockBatch::where('egg_size', $size)->sum('count');
        $committed = self::where('egg_size', $size)->where('status', 'pending')->sum('egg_count');

        return max(0, $logged - $stocked - $committed);
    }

    /**
     * Create a pre-order inside a DB transaction with row locking,
     * ensuring it doesn't over-commit available stock.
     *
     * Pool includes logged egg_size_logs (not just stocked batches),
     * matching EggStockBatch::getAvailablePoolForSize.
     *
     * @throws \OverflowException if requested egg_count exceeds available stock
     */
    public static function createWithinPool(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $available = self::getPoolWithLock($data['egg_size']);

            if (($data['egg_count'] ?? 0) > $available) {
                throw new \OverflowException(
                    "Only {$available} {$data['egg_size']} egg(s) available (total production minus stocked and other pending pre-orders)."
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

            if ($wasPending && !$isPending) {
                $this->update($data);
                return;
            }

            $available = self::getPoolWithLock($newSize);

            if ($newCount > $available) {
                throw new \OverflowException(
                    "Only {$available} {$newSize} egg(s) available (total production minus stocked and other pending pre-orders)."
                );
            }

            $this->update($data);
        });
    }
}
