<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Services\EnvironmentStatusService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $data = $this->buildDashboardData();

        return view('dashboard', $data);
    }

    public function stats()
    {
        $data = $this->buildDashboardData(request('cage'));

        return view('dashboard._metric-cards', $data);
    }

    public function feedMortality()
    {
        $data = $this->buildDashboardData(request('cage'));

        return view('dashboard._feed-mortality', $data);
    }

    public function calendar()
    {
        $cageCode = request('cage');
        $month = max(1, min(12, (int) request('month', now()->month)));
        $year = max(2000, (int) request('year', now()->year));
        $calendarMonth = now()->copy()->setDate($year, $month, 1)->startOfMonth();

        $monthStart = $calendarMonth->copy()->startOfMonth();
        $monthEnd = $calendarMonth->copy()->endOfMonth();
        // Leading/trailing days spill into adjacent months, so fetch a little
        // margin either side to cover every rendered cell.
        $rangeStart = $monthStart->copy()->subDays(6);
        $rangeEnd = $monthEnd->copy()->addDays(6);

        $scope = fn ($q) => $q->when($cageCode, fn ($cq) => $cq->whereHas('cageSlot.cage', fn ($c) => $c->where('cage_code', $cageCode)));

        $logs = ProductionLog::query()
            ->whereBetween('log_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->where($scope)
            ->get()
            ->groupBy(fn ($l) => $l->log_date->format('Y-m-d'))
            ->map(fn ($g) => (object) [
                'eggs' => $g->sum('egg_count'),
                'logs' => $g->count(),
            ]);

        $monthLogs = ProductionLog::query()
            ->whereBetween('log_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where($scope)
            ->get();
        $monthTotalEggs = $monthLogs->sum('egg_count');
        $monthLoggedDays = $monthLogs->groupBy(fn ($l) => $l->log_date->format('Y-m-d'))->count();

        // Year options for navigation: earliest recorded log .. next year.
        $firstLogDate = ProductionLog::query()->orderBy('log_date')->value('log_date');
        $firstYear = $firstLogDate ? (int) date('Y', strtotime($firstLogDate)) : now()->year;
        $yearOptions = range(max($firstYear, now()->year - 10), now()->year + 1);

        $cageOptions = Cage::query()->orderBy('cage_code')->get(['id', 'cage_code']);

        return view('dashboard._calendar', compact(
            'calendarMonth', 'logs', 'monthTotalEggs', 'monthLoggedDays', 'yearOptions', 'cageOptions', 'cageCode'
        ));
    }

    private function buildDashboardData(?string $cageCode = null): array
    {
        $today = now()->toDateString();
        $thresholds = Setting::thresholds();

        $needsOnboarding = Setting::where('key', 'farm_grid_rows')->doesntExist()
            || Setting::where('key', 'farm_grid_cols')->doesntExist();

        $cagesQuery = Cage::with([
            'productionLogs',
            'latestEnvironmentLog',
            'cageSlots.hardwareItems',
            'hardwareItems',
            'hens' => fn ($q) => $q->where('is_active', 1),
        ]);

        if ($cageCode) {
            $cagesQuery->where('cage_code', $cageCode);
        }

        $cages = $cagesQuery->get();

        // Attach today's stats to each cage
        $cages->each(function ($cage) use ($today) {
            $todayLog = $cage->productionLogs->where('log_date', $today)->first();
            $cage->today_hdep = $todayLog?->hdep ?? ($cage->latestProductionLog()?->hdep ?? 0);
            $cage->today_eggs = $cage->productionLogs
                ->filter(fn ($l) => $l->log_date && $l->log_date->toDateString() === $today)
                ->sum('egg_count');
            $cage->hen_count = $cage->hens->count();
            $cage->breed = $cage->hens->first()?->breed ?? '—';
            $cage->has_sensor = $cage->cageSlots->contains(fn ($s) => $s->hasBreakbeam()) || $cage->hasDht22();
            $cage->sensor_status = $this->sensorStatusText($cage);
        });

        // Total active hens
        if ($cageCode) {
            $totalHens = $cages->sum('hen_count');
            $todayLogs = $cages->flatMap->productionLogs->where('log_date', $today);
            $todayHdep = $todayLogs->count()
                ? round($todayLogs->avg('hdep'), 1)
                : round($cages->avg('today_hdep'), 1);
            $yesterdayLogs = $cages->flatMap->productionLogs->where('log_date', now()->subDay()->toDateString());
            $yesterdayHdep = $yesterdayLogs->count() ? round($yesterdayLogs->avg('hdep'), 1) : 0;
            $hdepDelta = round($todayHdep - $yesterdayHdep, 1);
            $eggsToday = $todayLogs->sum('egg_count') ?: $cages->sum('today_eggs');
            $lifetimeEggs = $cages->flatMap->productionLogs->sum('egg_count');
        } else {
            $totalHens = \App\Models\Hen::where('is_active', 1)->count();
            $todayLogs = ProductionLog::whereDate('log_date', $today)->get();
            $todayHdep = $todayLogs->count()
                ? round($todayLogs->avg('hdep'), 1)
                : round($cages->sum(fn ($c) => $c->today_hdep) / max($cages->count(), 1), 1);
            $yesterdayLogs = ProductionLog::whereDate('log_date', now()->subDay()->toDateString())->get();
            $yesterdayHdep = $yesterdayLogs->count() ? round($yesterdayLogs->avg('hdep'), 1) : 0;
            $hdepDelta = round($todayHdep - $yesterdayHdep, 1);
            $eggsToday = $todayLogs->sum('egg_count')
                ?: $cages->sum(fn ($c) => $c->today_eggs);
            $lifetimeEggs = ProductionLog::sum('egg_count');
        }

        // Coop environment averages
        $latestEnv = EnvironmentalLog::whereIn('cage_id', $cages->pluck('id'))
            ->orderByDesc('recorded_at')
            ->limit($cages->count())
            ->get();
        $avgTemp = $latestEnv->count() ? round($latestEnv->avg('temperature_c'), 1) : null;
        $avgHum = $latestEnv->count() ? round($latestEnv->avg('humidity_pct'), 1) : null;

        // Feed today — sum ALL of today's logs per cage (not just the most recent)
        $feedPerHenKg = (float) Setting::get('feed_per_hen_daily', 0.12);

        $feedToday = FeedConsumptionLog::with('cage')
            ->whereDate('log_date', $today)
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->orderByDesc('log_date')
            ->get()
            ->groupBy(fn ($f) => $f->cage?->cage_code ?? 'Deleted Cage')
            ->map(fn ($g) => (object) [
                'cage' => $g->first()->cage,
                'feed_consumed_kg' => round($g->sum('feed_consumed_kg'), 2),
                'hen_count' => $g->first()->cage?->hens->where('is_active', 1)->count() ?? 0,
                'feed_target_kg' => round($g->first()->cage?->hens->where('is_active', 1)->count() ?? 0, 2) * $feedPerHenKg,
            ]);

        // Mortality today
        $mortalityToday = MortalityLog::with('cage')
            ->whereDate('log_date', $today)
            ->when($cageCode, fn ($q) => $q->whereHas('cage', fn ($cq) => $cq->where('cage_code', $cageCode)))
            ->get()
            ->groupBy(fn ($l) => $l->cage?->cage_code ?? 'Deleted Cage')
            ->map(fn ($g) => $g->sum('count'));
        $mortalityTodayTotal = $mortalityToday->sum();

        // Live readings per cage
        $liveReadings = $cages->map(function ($cage) use ($thresholds) {
            $env = $cage->latestEnvironmentLog;
            if (! $env) {
                return null;
            }

            $status = EnvironmentStatusService::summary(
                $env->temperature_c,
                $env->humidity_pct,
                $thresholds
            );

            return (object) [
                'cage' => $cage->cage_code,
                'color' => $cage->color,
                'temp' => $env->temperature_c . '°C',
                'hum' => $env->humidity_pct . '%',
                'status' => $status,
            ];
        })->filter();

        return compact(
            'cages', 'totalHens', 'todayHdep', 'hdepDelta',
            'eggsToday', 'lifetimeEggs', 'avgTemp', 'avgHum', 'feedToday',
            'mortalityToday', 'mortalityTodayTotal',
            'liveReadings', 'today',
            'needsOnboarding'
        );
    }

    /**
     * Human-readable sensor status for a cage.
     * IR break-beam sensors have no heartbeat data, so "reporting" is based on
     * their active/faulty status; DHT22 recency comes from environmental logs.
     */
    private function sensorStatusText(Cage $cage): string
    {
        $assigned = $cage->cageSlots
            ->flatMap(fn ($s) => $s->hardwareItems)
            ->merge($cage->hardwareItems)
            ->whereIn('status', ['active', 'faulty']);

        $total = $assigned->count();
        if ($total === 0) {
            return 'No sensors installed';
        }

        $reporting = $assigned->where('status', 'active')->count();
        $text = "{$reporting} of {$total} sensor" . ($total > 1 ? 's' : '') . ' reporting';

        if ($cage->hasDht22()) {
            $env = $cage->latestEnvironmentLog;
            if ($env && $env->recorded_at->gt(now()->subMinutes(60))) {
                $text .= ' · DHT22 online — last reading ' . $env->recorded_at->diffForHumans();
            } elseif ($env) {
                $since = $env->recorded_at->isToday()
                    ? $env->recorded_at->format('g:i A')
                    : $env->recorded_at->format('M j, g:i A');
                $text .= " · DHT22 offline — no data since {$since}";
            } else {
                $text .= ' · DHT22 offline — no data yet';
            }
        }

        return $text;
    }
}
