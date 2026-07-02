<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Cage extends Model
{
    protected $fillable = ['cage_code', 'location', 'rows', 'slots_per_row', 'max_chickens_per_slot', 'total_capacity', 'is_active', 'location_row', 'location_column'];

    protected $casts = ['is_active' => 'boolean'];

    public function cageSlots(): HasMany
    {
        return $this->hasMany(CageSlot::class);
    }

    public function hens(): HasManyThrough
    {
        return $this->hasManyThrough(Hen::class, CageSlot::class);
    }

    public function productionLogs(): HasManyThrough
    {
        return $this->hasManyThrough(ProductionLog::class, CageSlot::class);
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

    public function eggStockBatches(): HasMany
    {
        return $this->hasMany(EggStockBatch::class);
    }

    public function hardwareItems(): HasMany
    {
        return $this->hasMany(HardwareItem::class);
    }

    public function hasDht22(): bool
    {
        if ($this->relationLoaded('hardwareItems')) {
            return $this->hardwareItems
                ->where('device_type', 'DHT22')
                ->where('status', 'active')
                ->isNotEmpty();
        }
        return $this->hardwareItems()
            ->where('device_type', 'DHT22')
            ->where('status', 'active')
            ->exists();
    }

    public function latestProductionLog()
    {
        return $this->productionLogs->sortByDesc('log_date')->first();
    }

    public function latestEnvironmentLog()
    {
        return $this->hasOne(EnvironmentalLog::class)->latestOfMany('recorded_at');
    }

    public function getHdepColorAttribute(): string
    {
        $hdep = optional($this->latestProductionLog())->hdep ?? 0;
        if ($hdep > 70) {
            return '#D5E8D4';
        }
        if ($hdep > 40) {
            return '#FFF3CD';
        }

        return '#F8D7DA';
    }

    public function getHdepTextColorAttribute(): string
    {
        $hdep = optional($this->latestProductionLog())->hdep ?? 0;
        if ($hdep > 70) {
            return '#004F9F';
        }
        if ($hdep > 40) {
            return '#856404';
        }

        return '#721C24';
    }

    public function getColorAttribute(): string
    {
        return match ($this->cage_code) {
            'CAGE-A' => '#2D7D46',
            'CAGE-B' => '#1D4E8F',
            'CAGE-C' => '#C2703E',
            'CAGE-D' => '#6B4C8A',
            default => $this->generateColor(),
        };
    }

    public function getColorSoftAttribute(): string
    {
        return match ($this->cage_code) {
            'CAGE-A' => '#d6f0e3',
            'CAGE-B' => '#dcebfa',
            'CAGE-C' => '#fae3d0',
            'CAGE-D' => '#e9e0f5',
            default => $this->generateSoftColor(),
        };
    }

    private function generateColor(): string
    {
        $hash = crc32($this->cage_code);
        $r = ($hash & 0xFF0000) >> 16;
        $g = ($hash & 0x00FF00) >> 8;
        $b = $hash & 0x0000FF;
        $r = (int) ($r * 0.5 + 64);
        $g = (int) ($g * 0.5 + 64);
        $b = (int) ($b * 0.5 + 64);

        return sprintf('#%02X%02X%02X', min($r, 180), min($g, 180), min($b, 180));
    }

    private function generateSoftColor(): string
    {
        $hash = crc32($this->cage_code.'_soft');
        $r = ($hash & 0xFF0000) >> 16;
        $g = ($hash & 0x00FF00) >> 8;
        $b = $hash & 0x0000FF;
        $r = (int) ($r * 0.3 + 200);
        $g = (int) ($g * 0.3 + 200);
        $b = (int) ($b * 0.3 + 200);

        return sprintf('#%02X%02X%02X', min($r, 245), min($g, 245), min($b, 245));
    }

    public function getPrimaryHenAttribute(): ?Hen
    {
        return $this->hens()->where('is_active', 1)->first();
    }

    public function getFormattedLocationAttribute(): string
    {
        if (is_null($this->location_row) || is_null($this->location_column)) {
            return 'Unplaced';
        }

        return 'Row '.($this->location_row + 1).', Col '.($this->location_column + 1);
    }
}
