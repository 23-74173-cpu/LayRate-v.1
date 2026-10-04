<?php

namespace App\Models;

use App\Enums\EggSize;
use App\Services\EggPricingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PreOrder extends Model
{
    /** Eggs per commercial tray — also mirrored as EGGS_PER_TRAY in JS. */
    public const EGGS_PER_TRAY = 30;

    protected $fillable = [
        'customer_name',
        'customer_reference',
        'egg_size',
        'egg_count',
        'requested_date',
        'fulfillment_date',
        'status',
        'notes',
        'unit_price_tray',
        'unit_price_piece',
        'total_amount',
        'tray_size',
    ];

    protected $casts = [
        'egg_count' => 'integer',
        'requested_date' => 'date',
        'fulfillment_date' => 'date',
        'unit_price_tray' => 'decimal:2',
        'unit_price_piece' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function getTrayCountAttribute(): int
    {
        return (int) ceil($this->egg_count / self::EGGS_PER_TRAY);
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

        $trays = round($count / self::EGGS_PER_TRAY, 1);
        return number_format($count) . ' eggs (' . $trays . ' trays)';
    }

    public function getEggLabelAttribute(): string
    {
        return self::eggLabel($this->egg_count);
    }

    /**
     * Compute available pool for a size with row-level locking.
     *
     * Formula: SUM(egg_stock_batches.count) - SUM(pending pre_orders.egg_count)
     */
    private static function getPoolWithLock(string $size): int
    {
        EggStockBatch::where('egg_size', $size)->lockForUpdate()->get();
        self::where('egg_size', $size)->where('status', 'pending')->lockForUpdate()->get();

        $stocked = EggStockBatch::where('egg_size', $size)->sum('count');
        $committed = self::where('egg_size', $size)->where('status', 'pending')->sum('egg_count');

        return max(0, $stocked - $committed);
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
            $available = self::getPoolWithLock($data['egg_size']);

            if (($data['egg_count'] ?? 0) > $available) {
                throw new \OverflowException(
                    "Only {$available} " . EggSize::labelFor($data['egg_size']) . " egg(s) in stock (after subtracting other pending pre-orders)."
                );
            }

            // Server-authoritative price snapshot; anything from the browser
            // is ignored (the form never sends a total).
            $data = array_merge($data, self::priceSnapshot(
                $data['egg_size'], (int) $data['egg_count']
            ));

            return self::create($data);
        });
    }

    /**
     * Snapshot the current prices for a size+count. All-null when the size
     * has no tray price — callers must never backfill old rows with this.
     */
    public static function priceSnapshot(string $size, int $count): array
    {
        $price = EggPrice::where('egg_size', $size)->first();

        return EggPricingService::snapshotFor(
            $size,
            $count,
            $price?->price_per_tray,
            $price?->price_per_piece,
            self::EGGS_PER_TRAY
        );
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

            // Re-snapshot only when the priced inputs changed and the order
            // stays open; fulfilled/cancelled snapshots are frozen history.
            if (($newSize !== $oldSize || $newCount !== $oldCount) && $isPending) {
                $data = array_merge($data, self::priceSnapshot($newSize, $newCount));
            }

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
                    "Only {$available} " . EggSize::labelFor($newSize) . " egg(s) in stock (after subtracting other pending pre-orders)."
                );
            }

            $this->update($data);
        });
    }
}
