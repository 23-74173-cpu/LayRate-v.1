<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class HardwareItem extends Model
{
    // Safety-net TTL for the ingestion lookup cache below — invalidation on
    // write (HardwareItemController::update/destroy) is the primary
    // mechanism, this just bounds staleness if an invalidation call is ever
    // missed on some future write path.
    const INGESTION_CACHE_TTL = 300;
    protected $fillable = [
        'device_type',
        'serial_number',
        'cage_id',
        'cage_slot_id',
        'device_id',
        'installation_date',
        'status',
        'last_calibration_date',
    ];

    protected function casts(): array
    {
        return [
            'installation_date'     => 'date',
            'last_calibration_date' => 'date',
        ];
    }

    const DEVICE_TYPES = ['DHT22', 'IR_breakbeam', 'relay', 'other'];

    const STATUSES = ['active', 'faulty', 'removed', 'spare'];

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function latestOccupancyReading(): HasOne
    {
        return $this->hasOne(SensorOccupancyReading::class)->latestOfMany('recorded_at');
    }

    /**
     * Sensors that can be assigned from inventory:
     * explicitly spare, or active-but-currently-unassigned.
     */
    public function scopeAvailableForAssignment($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'spare')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'active')
                     ->whereNull('cage_id')
                     ->whereNull('cage_slot_id');
              });
        });
    }

    public static function ingestionCacheKey(string $serialNumber, int $deviceId): string
    {
        return "hw_item_ingest:{$serialNumber}:{$deviceId}";
    }

    /**
     * Resolve the active hardware item for a sensor-ingestion reading,
     * cached — this exact (serial_number, device_id, status='active') query
     * ran on every single reading (~1Hz per sensor) even though hardware
     * assignment changes only through the admin UI, rarely. Eager-loads
     * cageSlot, which also removes the lazy $hardwareItem->cageSlot query
     * from the IR breakbeam path in SensorIngestionController.
     *
     * Deliberately does NOT eager-load cageSlot.hens. active_hen_count is
     * used downstream to compute HDEP on every reading, and unlike hardware
     * assignment, hen placement/culling/mortality/removal happen from
     * several other controllers (Chickens, Cage, Mortality) that have no
     * reason to know about — or invalidate — this cache. Caching the hens
     * relation would let a hen change go unreflected in HDEP math for up to
     * INGESTION_CACHE_TTL seconds; leaving it un-eager-loaded means
     * CageSlot::getActiveHenCountAttribute() keeps running its own fresh
     * query every time, same as before this change. That's one query left
     * on the table on purpose — see SensorIngestionController for where.
     *
     * Not used for the DHT22 environment path directly, but harmless there
     * too since DHT22 items never have a cage_slot_id (see
     * HardwareItemController's validator), so the eager load is a no-op.
     */
    public static function findActiveForIngestion(string $serialNumber, int $deviceId): ?self
    {
        return Cache::remember(
            self::ingestionCacheKey($serialNumber, $deviceId),
            self::INGESTION_CACHE_TTL,
            fn () => static::where('serial_number', $serialNumber)
                ->where('device_id', $deviceId)
                ->where('status', 'active')
                ->with('cageSlot')
                ->first()
        );
    }
}
