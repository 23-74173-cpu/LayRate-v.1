<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\MortalityLog;

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

        $exists = Alert::where('cage_id', $cageId)
            ->where('alert_type', 'mortality_spike')
            ->where('is_read', 0)
            ->whereDate('triggered_at', $logDate)
            ->exists();

        if ($exists) {
            return;
        }

        Alert::create([
            'cage_id'      => $cageId,
            'alert_type'   => 'mortality_spike',
            'message'      => "{$cageDailyTotal} hen(s) died on {$logDate} — mortality spike detected",
            'is_read'      => 0,
            'triggered_at' => now(),
        ]);
    }
}
