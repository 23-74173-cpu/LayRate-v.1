<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $cages = Cage::with(['latestEnvironment', 'slots'])->get();

        // Total hens = sum of real occupancy across every slot, not a per-cage all-or-nothing guess.
        $totalHens = CageSlot::sum('current_occupancy');

        // Today's average HDEP (joins through cage_slots since production_logs no longer has cage_id directly).
        $todayLogs = ProductionLog::whereDate('log_date', $today)->get();
        $todayHdep = $todayLogs->count() ? round($todayLogs->avg('hdep'), 1) : 0;

        // Yesterday comparison
        $yesterdayLogs = ProductionLog::whereDate('log_date', now()->subDay()->toDateString())->get();
        $yesterdayHdep = $yesterdayLogs->count() ? round($yesterdayLogs->avg('hdep'), 1) : 0;
        $hdepDelta     = round($todayHdep - $yesterdayHdep, 1);

        // Eggs collected today
        $eggsToday = $todayLogs->sum('egg_count');

        // Coop environment averages (environmental_logs is unaffected — still cage_id directly)
        $latestEnv = EnvironmentalLog::whereIn('cage_id', $cages->pluck('id'))
            ->orderByDesc('recorded_at')
            ->limit($cages->count())
            ->get();
        $avgTemp = $latestEnv->count() ? round($latestEnv->avg('temperature_c'), 1) : null;
        $avgHum  = $latestEnv->count() ? round($latestEnv->avg('humidity_pct'), 1) : null;

        // Feed today (unaffected — feed_consumption_logs is still cage_id directly)
        $feedToday = FeedConsumptionLog::with('cage')
            ->whereDate('log_date', $today)
            ->orWhereDate('log_date', now()->subDay()->toDateString())
            ->orderByDesc('log_date')
            ->get()
            ->groupBy(fn($f) => $f->cage->cage_code)
            ->map(fn($g) => $g->first());

        // Mortality today (unaffected — mortality_logs is still cage_id directly)
        $mortalityToday = MortalityLog::with('cage')
            ->whereDate('log_date', $today)
            ->get()
            ->groupBy(fn($l) => $l->cage->cage_code)
            ->map(fn($g) => $g->sum('count'));
        $mortalityTodayTotal = $mortalityToday->sum();

        // Alerts (unaffected — alerts is still cage_id directly)
        $alertCount   = Alert::where('is_read', false)->count();
        $recentAlerts = Alert::with('cage')
            ->orderByRaw('is_read ASC')
            ->orderByDesc('triggered_at')
            ->limit(4)
            ->get();

        // Live readings per cage (unaffected — latestEnvironment/color are still on Cage)
        $liveReadings = $cages->map(function ($cage) {
            $env = $cage->latestEnvironment;
            if (! $env) return null;
            $status = 'Normal';
            if ($env->temperature_c > 30 || $env->humidity_pct > 70) $status = 'Alert';
            elseif ($env->temperature_c > 28.5 || $env->humidity_pct >= 70) $status = 'Watch';
            return (object) [
                'cage'   => $cage->cage_code,
                'color'  => $cage->color,
                'temp'   => $env->temperature_c . '°C',
                'hum'    => $env->humidity_pct . '%',
                'status' => $status,
            ];
        })->filter();

        return view('dashboard', compact(
            'cages', 'totalHens', 'todayHdep', 'hdepDelta',
            'eggsToday', 'avgTemp', 'avgHum', 'feedToday',
            'mortalityToday', 'mortalityTodayTotal',
            'alertCount', 'recentAlerts', 'liveReadings', 'today'
        ));
    }
}
