<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class FeedBatch extends Model
{
    protected $fillable = ['batch_code', 'brand', 'crude_protein', 'total_quantity_kg', 'unit_cost', 'date_received', 'low_stock_threshold', 'notes'];

    protected $casts = ['date_received' => 'date', 'total_quantity_kg' => 'float', 'unit_cost' => 'float', 'low_stock_threshold' => 'float'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (FeedBatch $batch) {
            if ($batch->batch_code) {
                return;
            }

            $batch->batch_code = DB::transaction(function () {
                $year = now()->format('Y');
                $prefix = "F-{$year}-";

                $last = static::where('batch_code', 'like', "{$prefix}%")
                    ->lockForUpdate()
                    ->orderBy('batch_code', 'desc')
                    ->value('batch_code');

                $next = $last ? (int) substr($last, -3) + 1 : 1;

                return "{$prefix}" . str_pad($next, 3, '0', STR_PAD_LEFT);
            });
        });
    }

    public function consumptionLogs(): HasMany
    {
        return $this->hasMany(FeedConsumptionLog::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'cage_id');
    }

    public function getCpColorAttribute(): string
    {
        if ($this->crude_protein >= 17.5) return '#D5E8D4';
        if ($this->crude_protein >= 16.5) return '#FFF3CD';
        return '#F8D7DA';
    }

    public function getCpTextAttribute(): string
    {
        if ($this->crude_protein >= 17.5) return '#2D6A4F';
        if ($this->crude_protein >= 16.5) return '#856404';
        return '#721C24';
    }

    public function getTotalCostAttribute(): ?float
    {
        if ($this->unit_cost === null || $this->total_quantity_kg === null) {
            return null;
        }
        return round($this->unit_cost * $this->total_quantity_kg, 2);
    }

    public function getRemainingKgAttribute(): ?float
    {
        if ($this->total_quantity_kg === null) {
            return null;
        }
        $consumed = $this->consumptionLogs()->sum('feed_consumed_kg');
        return round(max(0, $this->total_quantity_kg - $consumed), 2);
    }

    public function getIsLowStockAttribute(): bool
    {
        if ($this->remaining_kg === null || $this->low_stock_threshold === null) {
            return false;
        }
        return $this->remaining_kg <= $this->low_stock_threshold;
    }
}
