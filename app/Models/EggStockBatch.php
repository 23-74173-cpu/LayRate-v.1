<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class EggStockBatch extends Model
{
    public static function getAvailablePool(?int $cageId = null, ?string $harvestedDate = null): int
    {
        $logged = ProductionLog::when($cageId, fn($q) => $q->whereHas('cageSlot', fn($sq) => $sq->where('cage_id', $cageId)))
            ->when($harvestedDate, fn($q) => $q->where('log_date', '<=', $harvestedDate))
            ->sum('egg_count');
        $stocked = self::when($cageId, fn($q) => $q->where('cage_id', $cageId))
            ->when($harvestedDate, fn($q) => $q->where('harvested_date', '<=', $harvestedDate))
            ->sum('count');

        return max(0, $logged - $stocked);
    }

    public static function getAvailablePoolForSize(string $size, bool $lockForUpdate = false, ?int $cageId = null, ?string $harvestedDate = null): int
    {
        if ($lockForUpdate) {
            $batchQuery = self::where('egg_size', $size);
            if ($cageId) $batchQuery->where('cage_id', $cageId);
            if ($harvestedDate) $batchQuery->where('harvested_date', '<=', $harvestedDate);
            $batchQuery->lockForUpdate()->get();

            $eggSizeLogQuery = \App\Models\EggSizeLog::where('egg_size', $size);
            if ($cageId) {
                $eggSizeLogQuery->whereHas('productionLog.cageSlot', fn($q) => $q->where('cage_id', $cageId));
            }
            if ($harvestedDate) {
                $eggSizeLogQuery->whereHas('productionLog', fn($q) => $q->where('log_date', '<=', $harvestedDate));
            }
            $eggSizeLogQuery->lockForUpdate()->get();

            if ($cageId === null) {
                $preOrderQuery = \App\Models\PreOrder::where('egg_size', $size)
                    ->where('status', 'pending');
                if ($harvestedDate) {
                    $preOrderQuery->where('created_at', '<=', $harvestedDate . ' 23:59:59');
                }
                $preOrderQuery->lockForUpdate()->get();
            }
        }

        $loggedQuery = \App\Models\EggSizeLog::where('egg_size', $size);
        if ($cageId) {
            $loggedQuery->whereHas('productionLog.cageSlot', fn($q) => $q->where('cage_id', $cageId));
        }
        if ($harvestedDate) {
            $loggedQuery->whereHas('productionLog', fn($q) => $q->where('log_date', '<=', $harvestedDate));
        }
        $logged = $loggedQuery->sum('count');

        $stockedQuery = self::where('egg_size', $size);
        if ($cageId) $stockedQuery->where('cage_id', $cageId);
        if ($harvestedDate) $stockedQuery->where('harvested_date', '<=', $harvestedDate);
        $stocked = $stockedQuery->sum('count');

        $preOrdered = 0;
        if ($cageId === null) {
            $preOrderQuery = \App\Models\PreOrder::where('egg_size', $size)
                ->where('status', 'pending');
            if ($harvestedDate) {
                $preOrderQuery->where('created_at', '<=', $harvestedDate . ' 23:59:59');
            }
            $preOrdered = $preOrderQuery->sum('egg_count');
        }

        return max(0, $logged - $stocked - $preOrdered);
    }

    public static function getAvailablePools(?int $cageId = null, ?string $harvestedDate = null): array
    {
        $sizes = ['small', 'medium', 'large', 'jumbo', 'unsorted'];
        $pools = [];
        foreach ($sizes as $size) {
            $pools[$size] = self::getAvailablePoolForSize($size, cageId: $cageId, harvestedDate: $harvestedDate);
        }
        return $pools;
    }

    public static function createWithinPool(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $available = self::getAvailablePoolForSize(
                $data['egg_size'],
                lockForUpdate: true,
                cageId: $data['cage_id'] ?? null,
                harvestedDate: $data['harvested_date'] ?? null
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
            $oldCount = (int) $this->getOriginal('count');
            $poolDate = $data['harvested_date'] ?? $this->harvested_date?->toDateString();

            if ($newSize === $oldSize && $newCount <= $oldCount) {
                $this->update($data);
                return;
            }

            if ($newSize !== $oldSize) {
                $available = self::getAvailablePoolForSize($newSize, lockForUpdate: true, cageId: $this->cage_id, harvestedDate: $poolDate);
                if ($newCount > $available) {
                    throw new \OverflowException(
                        "Only {$available} {$newSize} egg(s) available to stock."
                    );
                }
            } elseif ($newCount > $oldCount) {
                $increase = $newCount - $oldCount;
                $available = self::getAvailablePoolForSize($newSize, lockForUpdate: true, cageId: $this->cage_id, harvestedDate: $poolDate);
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

    public static function sizeThresholds(): array
    {
        $sizes = ['small', 'medium', 'large', 'jumbo', 'unsorted'];
        $thresholds = [];
        foreach ($sizes as $size) {
            $thresholds[$size] = (int) \App\Models\Setting::get("egg_low_stock_threshold_{$size}", 0);
        }
        return $thresholds;
    }

    public static function checkLowStock(): void
    {
        $pools = self::getAvailablePools();
        $thresholds = self::sizeThresholds();

        foreach ($pools as $size => $available) {
            $threshold = $thresholds[$size] ?? 0;
            if ($threshold <= 0) continue;
            if ($available > $threshold) continue;

            $exists = \App\Models\Alert::where('alert_type', 'low_stock_eggs')
                ->where('cage_id', null)
                ->where('is_read', 0)
                ->where('message', 'like', "%{$size}%")
                ->whereDate('triggered_at', today())
                ->exists();

            if ($exists) continue;

            \App\Models\Alert::createDeduped([
                'cage_id' => null,
                'alert_type' => 'low_stock_eggs',
                'message' => "Low stock: " . ucfirst($size) . " eggs — {$available} remaining (threshold: {$threshold})",
                'is_read' => 0,
                'triggered_at' => now(),
                'dedup_key' => \App\Models\Alert::dedupKey(null, 'low_stock_eggs', $size),
                'alert_day' => \App\Services\ReportingDateService::reportingDateString(),
            ]);
        }
    }

    public static function freshnessThresholds(): array
    {
        return [
            'fresh_days' => (int) \App\Models\Setting::get('egg_freshness_fresh_days', 7),
            'aging_days' => (int) \App\Models\Setting::get('egg_freshness_aging_days', 14),
        ];
    }

    public function getFreshnessStatusAttribute(): string
    {
        $days = (int) $this->harvested_date->diffInDays(now());
        $thresholds = self::freshnessThresholds();

        if ($days < 0 || $days <= $thresholds['fresh_days']) {
            return 'fresh';
        }

        if ($days <= $thresholds['aging_days']) {
            return 'aging';
        }

        return 'old';
    }
}
