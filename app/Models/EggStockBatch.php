<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class EggStockBatch extends Model
{
    /**
     * Farm-wide available egg pool across all sizes: total eggs logged
     * via ProductionLog minus total eggs already stocked.
     *
     * NOTE: Pool includes production from ALL cages regardless of is_active
     * status (including deleted/deactivated cages). This is intentional:
     * eggs were physically produced and can still be stocked even if the
     * source cage is no longer active. Historical production does not
     * become invalid when a cage is deactivated.
     */
    public static function getAvailablePool(): int
    {
        $logged = ProductionLog::sum('egg_count');
        $stocked = self::sum('count');

        return max(0, $logged - $stocked);
    }

    /**
     * Available eggs for a specific size: what was logged via egg_size_logs
     * minus what has already been stocked for that size
     * minus what is committed via pending pre-orders.
     *
     * This establishes egg_size_logs as the single source of truth for
     * production. Stock batches may only draw from what was actually
     * produced per size, preventing over-stocking a size beyond real production.
     *
     * NOTE: Same policy as getAvailablePool() — ALL cage production counts,
     * including inactive/deleted cages. Historical production remains valid.
     */
    public static function getAvailablePoolForSize(string $size, bool $lockForUpdate = false): int
    {
        if ($lockForUpdate) {
            self::where('egg_size', $size)->lockForUpdate()->get();
            \App\Models\PreOrder::where('egg_size', $size)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();
        }

        $logged = \App\Models\EggSizeLog::where('egg_size', $size)->sum('count');
        $stocked = self::where('egg_size', $size)->sum('count');
        $preOrdered = \App\Models\PreOrder::where('egg_size', $size)
            ->where('status', 'pending')
            ->sum('egg_count');

        return max(0, $logged - $stocked - $preOrdered);
    }

    /**
     * Return per-size available pools for all standard sizes.
     */
    public static function getAvailablePools(): array
    {
        $sizes = ['small', 'medium', 'large', 'jumbo'];
        $pools = [];
        foreach ($sizes as $size) {
            $pools[$size] = self::getAvailablePoolForSize($size);
        }
        return $pools;
    }

    /**
     * Create a stock batch inside a DB transaction with row locking,
     * preventing concurrent requests from the same size from over-committing.
     *
     * @throws \OverflowException if requested count exceeds available pool
     */
    public static function createWithinPool(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $available = self::getAvailablePoolForSize($data['egg_size'], lockForUpdate: true);

            if (($data['count'] ?? 0) > $available) {
                throw new \OverflowException(
                    "Only {$available} {$data['egg_size']} egg(s) available to stock."
                );
            }

            return self::create($data);
        });
    }

    /**
     * Update a stock batch inside a DB transaction with row locking.
     * Handles size changes: return to old-size pool, deduct from new-size pool.
     *
     * @throws \OverflowException if the requested change exceeds available pool
     */
    public function updateWithinPool(array $data): void
    {
        DB::transaction(function () use ($data) {
            $newSize = $data['egg_size'] ?? $this->egg_size;
            $newCount = (int) ($data['count'] ?? $this->count);
            $oldSize = $this->getOriginal('egg_size');
            $oldCount = $this->getOriginal('count');

            if ($newSize === $oldSize && $newCount <= $oldCount) {
                $this->update($data);
                return;
            }

            if ($newSize !== $oldSize) {
                $available = self::getAvailablePoolForSize($newSize, lockForUpdate: true);
                if ($newCount > $available) {
                    throw new \OverflowException(
                        "Only {$available} {$newSize} egg(s) available to stock."
                    );
                }
            } elseif ($newCount > $oldCount) {
                $increase = $newCount - $oldCount;
                $available = self::getAvailablePoolForSize($newSize, lockForUpdate: true);
                if ($increase > $available) {
                    throw new \OverflowException(
                        "Only {$available} additional {$newSize} egg(s) available to stock."
                    );
                }
            }

            $this->update($data);
        });
    }
    protected $fillable = [
        'egg_size',
        'count',
        'harvested_date',
        'cage_id',
        'cage_slot_id',
        'source_production_log_id',
    ];

    protected $casts = [
        'count' => 'integer',
        'harvested_date' => 'date',
    ];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function sourceProductionLog(): BelongsTo
    {
        return $this->belongsTo(ProductionLog::class, 'source_production_log_id');
    }

    public function getFreshnessStatusAttribute(): string
    {
        $days = (int) $this->harvested_date->diffInDays(now());

        if ($days < 0) {
            return 'fresh';
        }

        if ($days <= 7) {
            return 'fresh';
        }

        if ($days <= 14) {
            return 'aging';
        }

        return 'old';
    }
}
