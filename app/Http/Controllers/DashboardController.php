<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $cages = Cage::with([
            'productionLogs',
            'latestEnvironmentLog',
            'hens' => fn($q) => $q->where('is_active', 1),
        ])->get();

        // Total active hens (sum of total_capacity across all cages)
        $totalHens = $cages->sum('total_capacity');

        // Today's average HDEP
        $todayLogs = ProductionLog::whereDate('log_date', $today)->get();
        $todayHdep = $todayLogs->count()
            ? round($todayLogs->avg('hdep'), 1)
            : round($cages->sum(fn($c) => $c->latestProductionLog()?->hdep ?? 0) / max($cages->count(), 1), 1);

        // Yesterday comparison
        $yesterdayLogs  = ProductionLog::whereDate('log_date', now()->subDay()->toDateString())->get();
        $yesterdayHdep  = $yesterdayLogs->count() ? round($yesterdayLogs->avg('hdep'), 1) : 0;
        $hdepDelta      = round($todayHdep - $yesterdayHdep, 1);

        // Eggs collected today
        $eggsToday = $todayLogs->sum('egg_count')
            ?: $cages->sum(fn($c) => $c->latestProductionLog()?->egg_count ?? 0);

        // Coop environment averages
        $latestEnv = EnvironmentalLog::whereIn('cage_id', $cages->pluck('id'))
            ->orderByDesc('recorded_at')
            ->limit($cages->count())
            ->get();
        $avgTemp = $latestEnv->count() ? round($latestEnv->avg('temperature_c'), 1) : null;
        $avgHum  = $latestEnv->count() ? round($latestEnv->avg('humidity_pct'), 1) : null;

        // Feed today
        $feedToday = FeedConsumptionLog::with('cage')
            ->whereDate('log_date', $today)
            ->orWhereDate('log_date', now()->subDay()->toDateString())
            ->orderByDesc('log_date')
            ->get()
            ->groupBy(fn($f) => $f->cage->cage_code)
            ->map(fn($g) => $g->first());

        // Mortality today
        $mortalityToday = MortalityLog::with('cage')
            ->whereDate('log_date', $today)
            ->get()
            ->groupBy(fn($l) => $l->cage->cage_code)
            ->map(fn($g) => $g->sum('count'));
        $mortalityTodayTotal = $mortalityToday->sum();

        // Alerts
        $alertCount   = Alert::where('is_read', false)->count();
        $recentAlerts = Alert::with('cage')
            ->orderByRaw('is_read ASC')
            ->orderByDesc('triggered_at')
            ->limit(4)
            ->get();

        // Live readings per cage
        $liveReadings = $cages->map(function ($cage) {
            $env = $cage->latestEnvironmentLog;
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
