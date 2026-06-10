<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cage extends Model
{
    protected $fillable = ['cage_code', 'location', 'capacity', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function hens(): HasMany
    {
        return $this->hasMany(Hen::class);
    }

    public function productionLogs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
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

    public function latestProduction()
    {
        return $this->hasOne(ProductionLog::class)->latestOfMany('log_date');
    }

    public function latestEnvironment()
    {
        return $this->hasOne(EnvironmentalLog::class)->latestOfMany('recorded_at');
    }

    public function getHdepColorAttribute(): string
    {
        $hdep = $this->latestProduction?->hdep ?? 0;
        if ($hdep > 70) return '#D5E8D4';
        if ($hdep > 40) return '#FFF3CD';
        return '#F8D7DA';
    }

    public function getHdepTextColorAttribute(): string
    {
        $hdep = $this->latestProduction?->hdep ?? 0;
        if ($hdep > 70) return '#004F9F';
        if ($hdep > 40) return '#856404';
        return '#721C24';
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
