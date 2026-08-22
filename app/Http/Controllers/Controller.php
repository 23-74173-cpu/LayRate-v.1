<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\MortalityLog;
use App\Services\ReportingDateService;

abstract class Controller
{
    protected function checkMortalitySpike(int $cageId, string $logDate): void
    {
        $cageDailyTotal = MortalityLog::where('cage_id', $cageId)
            ->whereDate('log_date', $logDate)
            ->sum('count');

        if ($cageDailyTotal < 3) {
            return;
        }

        [$dayStart, $dayEnd] = ReportingDateService::reportingDayWindow($logDate);

        $exists = Alert::where('cage_id', $cageId)
            ->where('alert_type', 'mortality_spike')
            ->where('is_read', 0)
            ->where('triggered_at', '>=', $dayStart)
            ->where('triggered_at', '<', $dayEnd)
            ->exists();

        if ($exists) {
            return;
        }

        Alert::createDeduped([
            'cage_id'      => $cageId,
            'alert_type'   => 'mortality_spike',
            'message'      => "{$cageDailyTotal} hen(s) died on {$logDate} — mortality spike detected",
            'is_read'      => 0,
            'triggered_at' => now(),
            'dedup_key'    => Alert::dedupKey($cageId, 'mortality_spike'),
            'alert_day'    => $logDate,
        ]);
    }
}
