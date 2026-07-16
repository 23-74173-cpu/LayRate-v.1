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

    /**
     * Create a pre-order inside a DB transaction with row locking,
     * ensuring it doesn't over-commit the shared pool with stock batches.
     *
     * @throws \OverflowException if requested egg_count exceeds available pool
     */
    public static function createWithinPool(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $available = EggStockBatch::getAvailablePoolForSize($data['egg_size'], lockForUpdate: true);

            if (($data['egg_count'] ?? 0) > $available) {
                throw new \OverflowException(
                    "Only {$available} {$data['egg_size']} egg(s) available (production minus stocked and other pending pre-orders)."
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

            if ($wasPending && (!$isPending || $newSize !== $oldSize)) {
                if (!$isPending) {
                    $this->update($data);
                    return;
                }
            }

            $available = EggStockBatch::getAvailablePoolForSize($newSize, lockForUpdate: true);
            if ($newCount > $available) {
                throw new \OverflowException(
                    "Only {$available} {$newSize} egg(s) available (production minus stocked and other pending pre-orders)."
                );
            }

            $this->update($data);
        });
    }
}
