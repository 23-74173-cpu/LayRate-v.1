<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\FeedConsumptionLog;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Services\EnvironmentStatusService;
use App\Services\ReportingDateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Mandatory first-run setup (admin only): send to the wizard until it
        // has run once. Guarded by the zero-cage check so once setup is done
        // (or cages already exist) users are never blocked.
        if (auth()->user()?->isAdmin()
            && Setting::get('setup_completed') != '1'
            && Cage::count() === 0) {
            return redirect()->route('setup');
        }

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
        $now = ReportingDateService::now();
        $month = max(1, min(12, (int) request('month', $now->month)));
        $year = max(2000, (int) request('year', $now->year));
        $calendarMonth = $now->copy()->setDate($year, $month, 1)->startOfMonth();

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
        $firstYear = $firstLogDate ? (int) date('Y', strtotime($firstLogDate)) : $now->year;
        $yearOptions = range(max($firstYear, $now->year - 10), $now->year + 1);

        $cageOptions = Cage::query()->orderBy('cage_code')->get(['id', 'cage_code']);

        return view('dashboard._calendar', compact(
            'calendarMonth', 'logs', 'monthTotalEggs', 'monthLoggedDays', 'yearOptions', 'cageOptions', 'cageCode'
        ));
    }

    public function cagePerformance()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 1);
        if (! in_array($days, [1, 7, 14, 30])) {
            $days = 1;
        }

        $data = $this->buildDashboardData($cageCode);

        // Override per-cage stats for the selected period.
        $reportingDate = ReportingDateService::reportingDate();
        $endDate = $reportingDate->toDateString();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();

        $cageIds = $data['cages']->pluck('id');

        $periodStats = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->whereIn('cage_slots.cage_id', $cageIds)
            ->whereBetween('production_logs.log_date', [$startDate, $endDate])
            ->selectRaw('cage_slots.cage_id as cage_id, SUM(production_logs.egg_count) as total_eggs, AVG(production_logs.hdep) as avg_hdep')
            ->groupBy('cage_slots.cage_id')
            ->get()
            ->keyBy('cage_id');

        $data['cages']->each(function ($cage) use ($periodStats, $days) {
            $stats = $periodStats->get($cage->id);
            $cage->period_eggs = (int) ($stats?->total_eggs ?? 0);
            $cage->period_hdep = $cage->hen_count > 0 && $days > 0
                ? round($cage->period_eggs / ($cage->hen_count * $days) * 100, 1)
                : 0;
            // Fall back to stored avg_hdep if available and computed value is zero but eggs exist.
            if ($cage->period_hdep === 0.0 && $stats?->avg_hdep !== null) {
                $cage->period_hdep = round((float) $stats->avg_hdep, 1);
            }
        });

        $data['days'] = $days;
        $data['cageCode'] = $cageCode;

        return view('dashboard._cage-performance', $data);
    }

    public function productionHistory()
    {
        $cageCode = request('cage');
        $days = (int) request('days', 7);
        $compare = request('compare', false);
        if (! in_array($days, [7, 14, 30])) {
            $days = 7;
        }

        $reportingDate = ReportingDateService::reportingDate();
        $endDate = $reportingDate->toDateString();
        $startDate = $reportingDate->copy()->subDays($days - 1)->toDateString();

        // Build date labels and data points, filling missing days with 0.
        $labels = collect(range(0, $days - 1))
            ->map(fn ($i) => $reportingDate->copy()->subDays($days - 1 - $i)->format('M j'))
            ->values()
            ->toArray();

        $dateKeys = collect(range(0, $days - 1))
            ->map(fn ($i) => $reportingDate->copy()->subDays($days - 1 - $i)->toDateString())
            ->values();

        if ($compare) {
            // Multi-line comparison: one dataset per active cage.
            $cages = Cage::query()
                ->when($cageCode, fn ($q) => $q->where('cage_code', $cageCode))
                ->where('is_active', 1)
                ->orderBy('cage_code')
                ->get();

            $logs = ProductionLog::query()
                ->with('cageSlot.cage')
                ->whereBetween('log_date', [$startDate, $endDate])
                ->when($cageCode, fn ($q) => $q->whereHas('cageSlot.cage', fn ($c) => $c->where('cage_code', $cageCode)))
                ->get()
                ->groupBy(fn ($l) => $l->cageSlot?->cage?->cage_code ?? 'Unknown')
                ->map(fn ($group) => $group->groupBy(fn ($l) => $l->log_date->format('Y-m-d'))->map(fn ($g) => (int) $g->sum('egg_count')));

            $datasets = $cages->map(function ($cage) use ($dateKeys, $logs) {
                $cageLogs = $logs->get($cage->cage_code, collect());
                $color = $cage->color;
                $softColor = $cage->colorSoft;
                return [
                    'label' => $cage->cage_code,
                    'data' => $dateKeys->map(fn ($date) => $cageLogs->get($date, 0))->values()->toArray(),
                    'borderColor' => $softColor,
                    'backgroundColor' => $softColor,
                    'pointBorderColor' => $color,
                    'pointBorderWidth' => 2,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'fill' => false,
                ];
            })->values()->toArray();

            $title = $cageCode ? $cageCode . ' Production Comparison' : 'Cage Production Comparison';
        } else {
            // Same query pattern as the egg-management calendar (proven reliable).
            $scope = fn ($q) => $q->when($cageCode, fn ($cq) => $cq->whereHas('cageSlot.cage', fn ($c) => $c->where('cage_code', $cageCode)));

            $logs = ProductionLog::query()
                ->whereBetween('log_date', [$startDate, $endDate])
                ->where($scope)
                ->get()
                ->groupBy(fn ($l) => $l->log_date->format('Y-m-d'))
                ->map(fn ($g) => (int) $g->sum('egg_count'));

            $dataPoints = $dateKeys->map(fn ($date) => $logs->get($date, 0))->values()->toArray();

            $title = $cageCode ? $cageCode . ' Production' : 'Total Production';

            $datasets = [[
                'label' => $title,
                'data' => $dataPoints,
                'borderColor' => '#102A4C',
                'backgroundColor' => 'rgba(16, 42, 76, 0.1)',
                'tension' => 0.3,
                'borderWidth' => 3,
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'fill' => true,
            ]];
        }

        $chartData = [
            'labels' => $labels,
            'datasets' => $datasets,
        ];

        return view('dashboard._production-history', compact('chartData', 'days', 'cageCode', 'title', 'compare'));
    }

    public function buildDashboardData(?string $cageCode = null): array
    {
        $today = ReportingDateService::reportingDateString();
        $yesterday = ReportingDateService::reportingDate()->copy()->subDay()->toDateString();
        $thresholds = Setting::thresholds();

        $needsOnboarding = Setting::where('key', 'farm_grid_rows')->doesntExist()
            || Setting::where('key', 'farm_grid_cols')->doesntExist();

        // 'productionLogs' used to be eager-loaded here unconditionally — every
        // dashboard view (the most-visited page in the app) pulled every
        // production_logs row ever recorded, for every cage, into PHP memory,
        // just to filter it back down to "today's rows" per cage a few lines
        // below. That grows without bound for the life of the deployment.
        // Replaced with a single grouped SQL query below (today's per-cage
        // totals) plus, for the cage-scoped branch, two more small aggregate
        // queries (yesterday, lifetime) — nothing here loads a production_logs
        // row into PHP anymore; only pre-aggregated SUM/AVG/COUNT results do.
        $cagesQuery = Cage::with([
            'latestEnvironmentLog',
            'cageSlots.hardwareItems',
            'hardwareItems',
            'hens' => fn ($q) => $q->where('is_active', 1),
        ]);

        if ($cageCode) {
            $cagesQuery->where('cage_code', $cageCode);
        }

        $cages = $cagesQuery->get();
        $cageIds = $cages->pluck('id');

        $todayEggsByCage = ProductionLog::query()
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->whereIn('cage_slots.cage_id', $cageIds)
            ->whereDate('production_logs.log_date', $today)
            ->selectRaw('cage_slots.cage_id as cage_id, SUM(production_logs.egg_count) as total')
            ->groupBy('cage_slots.cage_id')
            ->pluck('total', 'cage_id');

        // Attach today's stats to each cage
        $cages->each(function ($cage) use ($todayEggsByCage) {
            $cage->today_eggs = (int) ($todayEggsByCage[$cage->id] ?? 0);
            $cage->hen_count = $cage->hens->count();
            // HDEP today = eggs collected again ÷ hens in the cage, as a percentage
            $cage->today_hdep = $cage->hen_count > 0 ? round($cage->today_eggs / $cage->hen_count * 100, 1) : 0;
            $cage->breed = $cage->hens->first()?->breed ?? '—';
            $cage->has_sensor = $cage->cageSlots->contains(fn ($s) => $s->hasBreakbeam()) || $cage->hasDht22();
            $cage->sensor_status = $this->sensorStatusText($cage);
        });

        // Total active hens
        if ($cageCode) {
            $totalHens = $cages->sum('hen_count');
            // HDEP today = eggs collected today ÷ all hens in the selected cages, as a percentage
            $todayHdep = $totalHens > 0 ? round($cages->sum('today_eggs') / $totalHens * 100, 1) : 0;

            $yesterdayStats = ProductionLog::query()
                ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
                ->whereIn('cage_slots.cage_id', $cageIds)
                ->whereDate('production_logs.log_date', $yesterday)
                ->selectRaw('COUNT(*) as log_count, AVG(production_logs.hdep) as avg_hdep, SUM(production_logs.egg_count) as total_eggs')
                ->first();
            $yesterdayHdep = $yesterdayStats->log_count ? round((float) $yesterdayStats->avg_hdep, 1) : 0;
            $eggsYesterday = (int) ($yesterdayStats->total_eggs ?? 0);

            $hdepDelta = round($todayHdep - $yesterdayHdep, 1);
            // Same source as today_eggs above (today's per-cage sum) — no
            // longer two independently-computed values with one as a
            // fallback for the other, since both used to derive from the
            // same eager-loaded collection anyway.
            $eggsToday = $cages->sum('today_eggs');
            $lifetimeEggs = ProductionLog::query()
                ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
                ->whereIn('cage_slots.cage_id', $cageIds)
                ->sum('production_logs.egg_count');
        } else {
            $totalHens = \App\Models\Hen::where('is_active', 1)->count();
            $todayLogs = ProductionLog::whereDate('log_date', $today)->get();
            // HDEP today = eggs collected today ÷ all hens in the cages, as a percentage
            $todayHdep = $totalHens > 0 ? round($todayLogs->sum('egg_count') / $totalHens * 100, 1) : 0;
            $yesterdayLogs = ProductionLog::whereDate('log_date', $yesterday)->get();
            $yesterdayHdep = $yesterdayLogs->count() ? round($yesterdayLogs->avg('hdep'), 1) : 0;
            $hdepDelta = round($todayHdep - $yesterdayHdep, 1);
            $eggsToday = $todayLogs->sum('egg_count')
                ?: $cages->sum(fn ($c) => $c->today_eggs);
            $eggsYesterday = $yesterdayLogs->sum('egg_count');
            $lifetimeEggs = ProductionLog::sum('egg_count');
        }

        $eggsDelta = round($eggsToday - $eggsYesterday);

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
                'colorSoft' => $cage->colorSoft,
                'temp' => $env->temperature_c . '°C',
                'hum' => $env->humidity_pct . '%',
                'status' => $status,
            ];
        })->filter();

        return compact(
            'cages', 'totalHens', 'todayHdep', 'hdepDelta',
            'eggsToday', 'eggsDelta', 'lifetimeEggs', 'avgTemp', 'avgHum', 'feedToday',
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

        $reporting = $assigned->where('health_state', 'online')->count();
        $text = "{$reporting} of {$total} sensor" . ($total > 1 ? 's' : '') . ' reporting';

        if ($cage->hasDht22()) {
            $env = $cage->latestEnvironmentLog;
            if ($env && $env->recorded_at->gt(ReportingDateService::now()->subMinutes(60))) {
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
