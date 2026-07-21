<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

        if ($count === 12) return '1 dozen';

        if ($count === 15) return 'half tray';

        if ($count % 30 === 0) {
            $trays = $count / 30;
            return $trays . ' ' . ($trays === 1 ? 'tray' : 'trays');
        }

        if ($count > 30 && $count % 15 === 0) {
            $halfTrays = $count / 15;
            return ($halfTrays / 2) . ' trays';
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
