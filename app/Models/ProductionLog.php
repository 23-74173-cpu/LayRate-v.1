<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['cage_slot_id', 'log_date', 'egg_count', 'hen_count', 'hdep', 'notes', 'logged_via', 'is_demo'];

    protected $casts = [
        'log_date' => 'date',
        'created_at' => 'datetime',
        'overridden_at' => 'datetime',
        'logged_via' => 'string',
        'is_demo' => 'boolean',
    ];

    public const LOGGED_VIA_OPTIONS = ['manual', 'sensor', 'unknown'];

    /**
     * Quarantine scope — DemoDataSeeder rows (is_demo = 1) are kept in the
     * table but excluded from forecasting reads so the Mar-Jun 2026
     * device-originated import is the sole source.
     */
    public function scopeReal($query)
    {
        return $query->where('is_demo', false);
    }

    public function cageSlot(): BelongsTo
    {
        return $this->belongsTo(CageSlot::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by_user_id');
    }

    public function getCageAttribute(): ?\App\Models\Cage
    {
        return $this->cageSlot?->cage;
    }

    public function eggSizeLogs(): HasMany
    {
        return $this->hasMany(EggSizeLog::class);
    }
}
