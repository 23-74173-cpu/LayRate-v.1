<?php

namespace App\Services;

use App\Models\HardwareItem;

/**
 * Build the canonical relay/fan state payload consumed by the Environment
 * SSE stream, the manual-control endpoint response, and the initial page
 * render — one shape everywhere so the frontend never re-derives it.
 */
class RelayStateService
{
    public static function payload(?HardwareItem $relay): array
    {
        if (! $relay) {
            return ['configured' => false];
        }

        $latest = $relay->cage?->latestEnvironmentLog;
        $seen = $relay->relay_seen_at;

        return [
            'configured' => true,
            'serial_number' => $relay->serial_number,
            'relay_status' => $relay->relay_status,
            'control_mode' => $relay->control_mode,
            'relay_safety' => (bool) $relay->relay_safety,
            'last_changed_at' => $relay->last_changed_at?->toIso8601String(),
            'last_changed_by' => $relay->lastChangedBy?->name,
            'temperature_c' => $latest?->temperature_c,
            'humidity_pct' => $latest?->humidity_pct,
            // online = heartbeating now; stale = heartbeating, but not recently;
            // (online=false && stale=false) = configured but never seen yet.
            'online' => $seen !== null && $seen->gt(now()->subMinutes(2)),
            'stale' => $seen !== null && $seen->lte(now()->subMinutes(2)),
        ];
    }
}
