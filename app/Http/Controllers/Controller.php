<?php

namespace App\Http\Controllers;
use App\Models\Alert;
use App\Models\Cage;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Services\ReportingDateService;
use App\Http\Controllers\Concerns\HandlesApiResponses;
use Carbon\Carbon;

abstract class Controller
{
    use HandlesApiResponses;

    protected function checkMortalitySpike(int $cageId, string $logDate): void
    {
        $threshold = (int) Setting::get('mortality_spike_threshold', 3);

        $cageDailyTotal = MortalityLog::where('cage_id', $cageId)
            ->whereDate('log_date', $logDate)
            ->sum('count');

        if ($cageDailyTotal < $threshold) {
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

    /**
     * Alert once when the cage's HDEP for the day, or the whole flock's,
     * reaches the Desired HDEP. After an alert the scope re-arms only when a
     * finished day with records ends below the target: today's own readings
     * never re-arm it (HDEP starts low every morning), and days with no
     * records (Pi offline, nothing logged) neither re-arm nor block it.
     * Only today and yesterday are evaluated (yesterday covers eggs logged
     * the next morning and sensor dates stamped in UTC), so imported or
     * back-filled history never raises this alert.
     */
    protected function checkHdepTarget(int $cageId, string $logDate): void
    {
        if ($logDate < ReportingDateService::reportingDate()->subDay()->toDateString()) {
            return;
        }

        $target = Setting::thresholds()['desired_hdep'];

        foreach ([$cageId, null] as $scopeCageId) {
            $hdep = $this->dailyHdep($scopeCageId, $logDate, $logDate)->get($logDate);
            if ($hdep === null || $hdep < $target) {
                continue;
            }

            $dedupKey = Alert::dedupKey($scopeCageId, 'hdep_target_reached');
            $lastAlertDay = Alert::where('dedup_key', $dedupKey)->max('alert_day');

            if ($lastAlertDay !== null) {
                $lastAlertDay = Carbon::parse($lastAlertDay)->toDateString();
                if ($lastAlertDay >= $logDate) {
                    continue;
                }

                $dayBefore = Carbon::parse($logDate)->subDay()->toDateString();
                $rearmed = $this->dailyHdep($scopeCageId, $lastAlertDay, $dayBefore)
                    ->contains(fn ($dayHdep) => $dayHdep < $target);
                if (! $rearmed) {
                    continue;
                }
            }

            $scopeName = $scopeCageId ? (Cage::whereKey($scopeCageId)->value('cage_code') ?? 'Cage') : 'All cages';
            $targetLabel = rtrim(rtrim(number_format($target, 1, '.', ''), '0'), '.');
            $hdepLabel = number_format($hdep, 1);
            $dateLabel = Carbon::parse($logDate)->format('M j, Y');

            Alert::createDeduped([
                'cage_id'      => $scopeCageId,
                'alert_type'   => 'hdep_target_reached',
                'message'      => "{$scopeName} reached the Desired HDEP: {$hdepLabel}% (target {$targetLabel}%) on {$dateLabel}.",
                'is_read'      => 0,
                'triggered_at' => now(),
                'dedup_key'    => $dedupKey,
                'alert_day'    => $logDate,
            ]);
        }
    }

    /**
     * Daily HDEP (eggs / hens × 100) per date for one cage, or for the whole
     * flock when $cageId is null. Dates without records are absent.
     */
    private function dailyHdep(?int $cageId, string $from, string $to): \Illuminate\Support\Collection
    {
        return ProductionLog::query()
            ->real()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->when($cageId, fn ($q) => $q->where('cage_slots.cage_id', $cageId))
            ->whereBetween('production_logs.log_date', [$from, $to])
            ->groupBy('production_logs.log_date')
            ->havingRaw('SUM(production_logs.hen_count) > 0')
            ->selectRaw('production_logs.log_date as day, SUM(production_logs.egg_count) * 100.0 / SUM(production_logs.hen_count) as hdep')
            ->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse($row->day)->toDateString() => (float) $row->hdep]);
    }
}
