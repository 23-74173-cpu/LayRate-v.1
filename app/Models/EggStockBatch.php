<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class EggStockBatch extends Model
{
    public static function getAvailablePool(?int $cageId = null): int
    {
        $logged = ProductionLog::when($cageId, fn($q) => $q->whereHas('cageSlot', fn($sq) => $sq->where('cage_id', $cageId)))
            ->sum('egg_count');
        $stocked = self::when($cageId, fn($q) => $q->where('cage_id', $cageId))
            ->sum('count');

        return max(0, $logged - $stocked);
    }

    public static function getAvailablePoolForSize(string $size, bool $lockForUpdate = false, ?int $cageId = null): int
    {
        if ($lockForUpdate) {
            $batchQuery = self::where('egg_size', $size);
            if ($cageId) $batchQuery->where('cage_id', $cageId);
            $batchQuery->lockForUpdate()->get();

            $eggSizeLogQuery = \App\Models\EggSizeLog::where('egg_size', $size);
            if ($cageId) {
                $eggSizeLogQuery->whereHas('productionLog.cageSlot', fn($q) => $q->where('cage_id', $cageId));
            }
            $eggSizeLogQuery->lockForUpdate()->get();

            if ($cageId === null) {
                \App\Models\PreOrder::where('egg_size', $size)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->get();
            }
        }

        $loggedQuery = \App\Models\EggSizeLog::where('egg_size', $size);
        if ($cageId) {
            $loggedQuery->whereHas('productionLog.cageSlot', fn($q) => $q->where('cage_id', $cageId));
        }
        $logged = $loggedQuery->sum('count');

        $stockedQuery = self::where('egg_size', $size);
        if ($cageId) $stockedQuery->where('cage_id', $cageId);
        $stocked = $stockedQuery->sum('count');

        $preOrdered = 0;
        if ($cageId === null) {
            $preOrdered = \App\Models\PreOrder::where('egg_size', $size)
                ->where('status', 'pending')
                ->sum('egg_count');
        }

        return max(0, $logged - $stocked - $preOrdered);
    }

    public static function getAvailablePools(?int $cageId = null): array
    {
        $sizes = ['small', 'medium', 'large', 'jumbo', 'unsorted'];
        $pools = [];
        foreach ($sizes as $size) {
            $pools[$size] = self::getAvailablePoolForSize($size, cageId: $cageId);
        }
        return $pools;
    }

    public static function createWithinPool(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $available = self::getAvailablePoolForSize(
                $data['egg_size'],
                lockForUpdate: true,
                cageId: $data['cage_id'] ?? null
            );

            if (($data['count'] ?? 0) > $available) {
                throw new \OverflowException(
                    "Only {$available} {$data['egg_size']} egg(s) available to stock."
                );
            }

            return self::create($data);
        });
    }

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
                $available = self::getAvailablePoolForSize($newSize, lockForUpdate: true, cageId: $this->cage_id);
                if ($newCount > $available) {
                    throw new \OverflowException(
                        "Only {$available} {$newSize} egg(s) available to stock."
                    );
                }
            } elseif ($newCount > $oldCount) {
                $increase = $newCount - $oldCount;
                $available = self::getAvailablePoolForSize($newSize, lockForUpdate: true, cageId: $this->cage_id);
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
