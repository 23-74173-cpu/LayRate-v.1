<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $gridRows = (int) Setting::get('farm_grid_rows', 4);
        $gridCols = (int) Setting::get('farm_grid_cols', 4);
        $needsOnboarding = Setting::where('key', 'farm_grid_rows')->doesntExist()
            || Setting::where('key', 'farm_grid_cols')->doesntExist()
            || Cage::count() === 0;

        $cages = Cage::with([
            'productionLogs',
            'latestEnvironmentLog',
            'cageSlots.hardwareItems',
            'hardwareItems',
            'hens' => fn ($q) => $q->where('is_active', 1),
        ])->get();

        // Attach today's stats to each cage
        $cages->each(function ($cage) use ($today) {
            $todayLog = $cage->productionLogs->where('log_date', $today)->first();
            $cage->today_hdep = $todayLog?->hdep ?? ($cage->latestProductionLog()?->hdep ?? 0);
            $cage->today_eggs = $todayLog?->egg_count ?? ($cage->latestProductionLog()?->egg_count ?? 0);
            $cage->hen_count = $cage->hens->count();
            $cage->breed = $cage->hens->first()?->breed ?? '—';
            $cage->has_sensor = $cage->cageSlots->contains(fn ($s) => $s->hasBreakbeam()) || $cage->hasDht22();
        });

        // Total active hens (actual live count, not theoretical capacity)
        $totalHens = \App\Models\Hen::where('is_active', 1)->count();

        // Today's average HDEP
        $todayLogs = ProductionLog::whereDate('log_date', $today)->get();
        $todayHdep = $todayLogs->count()
            ? round($todayLogs->avg('hdep'), 1)
            : round($cages->sum(fn ($c) => $c->today_hdep) / max($cages->count(), 1), 1);

        // Yesterday comparison
        $yesterdayLogs = ProductionLog::whereDate('log_date', now()->subDay()->toDateString())->get();
        $yesterdayHdep = $yesterdayLogs->count() ? round($yesterdayLogs->avg('hdep'), 1) : 0;
        $hdepDelta = round($todayHdep - $yesterdayHdep, 1);

        // Eggs collected today
        $eggsToday = $todayLogs->sum('egg_count')
            ?: $cages->sum(fn ($c) => $c->today_eggs);

        // Coop environment averages
        $latestEnv = EnvironmentalLog::whereIn('cage_id', $cages->pluck('id'))
            ->orderByDesc('recorded_at')
            ->limit($cages->count())
            ->get();
        $avgTemp = $latestEnv->count() ? round($latestEnv->avg('temperature_c'), 1) : null;
        $avgHum = $latestEnv->count() ? round($latestEnv->avg('humidity_pct'), 1) : null;

        // Feed today
        $feedToday = FeedConsumptionLog::with('cage')
            ->whereDate('log_date', $today)
            ->orWhereDate('log_date', now()->subDay()->toDateString())
            ->orderByDesc('log_date')
            ->get()
            ->groupBy(fn ($f) => $f->cage->cage_code)
            ->map(fn ($g) => $g->first());

        // Mortality today
        $mortalityToday = MortalityLog::with('cage')
            ->whereDate('log_date', $today)
            ->get()
            ->groupBy(fn ($l) => $l->cage->cage_code)
            ->map(fn ($g) => $g->sum('count'));
        $mortalityTodayTotal = $mortalityToday->sum();

        // Live readings per cage
        $liveReadings = $cages->map(function ($cage) {
            $env = $cage->latestEnvironmentLog;
            if (! $env) {
                return null;
            }
            $status = 'Normal';
            if ($env->temperature_c > 30 || $env->humidity_pct > 70) {
                $status = 'Alert';
            } elseif ($env->temperature_c > 28.5 || $env->humidity_pct >= 70) {
                $status = 'Watch';
            }

            return (object) [
                'cage' => $cage->cage_code,
                'color' => $cage->color,
                'temp' => $env->temperature_c . '°C',
                'hum' => $env->humidity_pct . '%',
                'status' => $status,
            ];
        })->filter();

        return view('dashboard', compact(
            'cages', 'totalHens', 'todayHdep', 'hdepDelta',
            'eggsToday', 'avgTemp', 'avgHum', 'feedToday',
            'mortalityToday', 'mortalityTodayTotal',
            'liveReadings', 'today',
            'gridRows', 'gridCols', 'needsOnboarding'
        ));
    }
}
