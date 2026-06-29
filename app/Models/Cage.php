<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Cage extends Model
{
    protected $fillable = ['cage_code', 'location', 'rows', 'slots_per_row', 'max_chickens_per_slot', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function slots(): HasMany
    {
        return $this->hasMany(CageSlot::class)->orderBy('slot_number');
    }

    public function hens(): HasManyThrough
    {
        return $this->hasManyThrough(Hen::class, CageSlot::class);
    }

    public function environmentalLogs(): HasMany
    {
        return $this->hasMany(EnvironmentalLog::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function feedConsumptionLogs(): HasMany
    {
        return $this->hasMany(FeedConsumptionLog::class);
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(Forecast::class);
    }

    public function latestEnvironment()
    {
        return $this->hasOne(EnvironmentalLog::class)->latestOfMany('recorded_at');
    }

    public function getTotalCapacityAttribute(): int
    {
        return $this->rows * $this->slots_per_row * $this->max_chickens_per_slot;
    }

    public function getColorAttribute(): string
    {
        return match($this->cage_code) {
            'CAGE-A' => '#2D7D46',
            'CAGE-B' => '#1D4E8F',
            'CAGE-C' => '#C2703E',
            'CAGE-D' => '#6B4C8A',
            default  => '#6B7280',
        };
    }

    public function getPrimaryHenAttribute(): ?Hen
    {
        return $this->hens()->where('is_active', 1)->first();
    }
}
