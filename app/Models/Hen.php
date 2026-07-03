<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Hen extends Model
{
    protected $fillable = [
        'cage_slot_id', 'tag_code', 'chicken_id', 'date_acquired', 'flock_age_weeks',
        'placement_date', 'age_at_placement_weeks', 'breed', 'sex',
        'source', 'initial_health_status', 'notes', 'is_active',
    ];

    protected $casts = [
        'date_acquired'  => 'date',
        'placement_date' => 'date',
        'is_active'      => 'boolean',
        'sex'            => 'string',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Hen $hen) {
            if ($hen->chicken_id) {
                return;
            }

            $hen->chicken_id = DB::transaction(function () {
                $year = now()->format('Y');
                $prefix = "CHK-{$year}-%";

                $last = Hen::where('chicken_id', 'like', "CHK-{$year}-%")
                    ->lockForUpdate()
                    ->orderBy('chicken_id', 'desc')
                    ->value('chicken_id');

                $next = $last ? (int) substr($last, -5) + 1 : 1;

                return sprintf("CHK-%s-%05d", $year, $next);
            });
        });
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function cageTransfers(): HasMany
    {
        return $this->hasMany(CageTransfer::class);
    }

    public function healthEvents(): HasMany
    {
        return $this->hasMany(HealthEvent::class);
    }

    public function weightChecks(): HasMany
    {
        return $this->hasMany(WeightCheck::class);
    }

    public function cullingLogs(): HasMany
    {
        return $this->hasMany(CullingLog::class);
    }

    public function removals(): HasMany
    {
        return $this->hasMany(Removal::class);
    }

    public function getCurrentAgeWeeksAttribute(): int
    {
        if ($this->placement_date !== null) {
            $weeksElapsed = (int) floor($this->placement_date->diffInWeeks(now()));
            return (int) $this->age_at_placement_weeks + $weeksElapsed;
        }

        return $this->flock_age_weeks;
    }

    public function getCageAttribute(): ?Cage
    {
        return $this->cageSlot?->cage;
    }
}
