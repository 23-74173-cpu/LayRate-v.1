<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DashboardController;
use App\Models\Cage;
use App\Models\FeedConsumptionLog;
use App\Models\ProductionLog;
use App\Services\ReportingDateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private function cageOrAll(Request $request): array
    {
        $period   = $request->get('period', 'week');

        $allCages = Cage::with(['hens' => fn($q) => $q->where('is_active', 1)])
            ->orderBy('cage_code')
            ->get();
        if ($allCages->isEmpty()) {
            return ['redirect' => redirect()->route('dashboard')->with('error', 'No cages configured. Create a cage first.')];
        }

        $cageCode = $request->get('cage', 'performance');

        $days = match($period) {
            'month'   => 30,
            '3months' => 90,
            'full'    => 0,
            default   => 7,
        };
        $isFull = $period === 'full';
        $rangeStart = $isFull ? null : ReportingDateService::reportingDate()->copy()->subDays($days)->toDateString();

        $isAll = $cageCode === 'all';
        $isPerformance = $cageCode === 'performance';

        if ($isPerformance) {
            $dashboardController = app(DashboardController::class);
            $data = $dashboardController->buildDashboardData(null);

            $reportingDate = ReportingDateService::reportingDate();
            $endDate = $reportingDate->toDateString();
            $startDate = $isFull
                ? $reportingDate->copy()->subYears(5)->toDateString()
                : $reportingDate->copy()->subDays($days - 1)->toDateString();

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
                if ($cage->period_hdep === 0.0 && $stats?->avg_hdep !== null) {
                    $cage->period_hdep = round((float) $stats->avg_hdep, 1);
                }
            });

            $cages = $data['cages'];
            $cage = null;
            $cageCode = null;
            $logs = collect();
            $feedLogs = collect();
            $avgHdep = '-';
            $bestDay = '-';
            $worstDay = '-';

            return compact('cage', 'cageCode', 'period', 'logs', 'feedLogs', 'avgHdep', 'bestDay', 'worstDay', 'allCages', 'isAll', 'isPerformance', 'days', 'cages');
        }

        if ($isAll) {
            $cageIds = $allCages->pluck('id');
            $logs = ProductionLog::whereIn('cage_slot_id', function ($q) use ($cageIds) {
                $q->select('id')->from('cage_slots')->whereIn('cage_id', $cageIds);
            })
                ->when($rangeStart, fn($q) => $q->where('log_date', '>=', $rangeStart))
                ->orderBy('log_date')
                ->get();

            $feedLogs = FeedConsumptionLog::whereIn('cage_id', $cageIds)
                ->when($rangeStart, fn($q) => $q->where('log_date', '>=', $rangeStart))
                ->orderBy('log_date')
                ->get();

            $totalHens = $allCages->sum('total_capacity');

            $cage = null;
        } else {
            $cage = Cage::with(['hens' => fn($q) => $q->where('is_active', 1)])
                ->where('cage_code', $cageCode)
                ->firstOrFail();

            $logs = $cage->productionLogs()
                ->when($rangeStart, fn($q) => $q->where('log_date', '>=', $rangeStart))
                ->orderBy('log_date')
                ->get();

            $feedLogs = FeedConsumptionLog::where('cage_id', $cage->id)
                ->when($rangeStart, fn($q) => $q->where('log_date', '>=', $rangeStart))
                ->orderBy('log_date')
                ->get();
        }

        $avgHdep  = $logs->count() ? round($logs->avg('hdep'), 1) : '-';
        $bestDay  = $logs->count() ? round($logs->max('hdep'), 1) : '-';
        $worstDay = $logs->count() ? round($logs->min('hdep'), 1) : '-';
        $isPerformance = false;
        $performance = collect();
        $topEggsCage = null;

        return compact('cage', 'cageCode', 'period', 'logs', 'feedLogs', 'avgHdep', 'bestDay', 'worstDay', 'allCages', 'isAll', 'isPerformance', 'days', 'performance', 'topEggsCage');
    }

    public function index(Request $request)
    {
        $data = $this->cageOrAll($request);
        if (isset($data['redirect'])) return $data['redirect'];

        return view('analytics', $data);
    }

    public function charts(Request $request)
    {
        $data = $this->cageOrAll($request);
        if (isset($data['redirect'])) return response('', 422);

        if (($data['isPerformance'] ?? false)) {
            return view('analytics._performance', $data);
        }

        return view('analytics._charts', $data);
    }

    public function data(Request $request)
    {
        $d = $this->cageOrAll($request);
        if (isset($d['redirect'])) return response()->json(['error' => 'No cages configured'], 422);

        if (($d['isPerformance'] ?? false)) {
            $performance = $d['cages']->map(function ($cage, int $i) {
                return [
                    'rank'       => $i + 1,
                    'cage_code'  => $cage->cage_code,
                    'color'      => $cage->color,
                    'breed'      => $cage->breed,
                    'avg_hdep'   => $cage->period_hdep,
                    'total_eggs' => (int) $cage->period_eggs,
                ];
            })->values();

            $topEggsCage = $performance->isNotEmpty()
                ? $performance->sortByDesc('total_eggs')->first()['cage_code']
                : null;

            return response()->json([
                'mode'        => 'performance',
                'period'      => $d['period'],
                'days'        => $d['days'],
                'performance' => $performance->map(fn($p) => array_merge($p, [
                    'avg_hdep'   => $p['avg_hdep'] === null ? null : (float) $p['avg_hdep'],
                    'total_eggs' => (int) $p['total_eggs'],
                ])),
                'topEggsCage' => $topEggsCage,
            ]);
        }

        $cageColor = $d['isAll'] ? '#002D5E' : $d['cage']->color;

        $logs = $d['logs']->map(fn($l) => [
            'date' => $l->log_date->format('Y-m-d'),
            'hdep' => (float) $l->hdep,
            'eggs' => (int) $l->egg_count,
        ])->values();

        $feedLogs = $d['feedLogs']->map(fn($l) => [
            'date' => $l->log_date->format('Y-m-d'),
            'kg' => (float) $l->feed_consumed_kg,
        ])->values();

        $hasLogs = $logs->isNotEmpty();
        $hasFeedOverlap = $hasLogs && $feedLogs->isNotEmpty();

        $breed = '—';
        $flockAge = '—';
        if (!$d['isAll'] && $d['cage']) {
            $hen = $d['cage']->hens->first();
            $breed = $hen?->breed ?? '—';
            $flockAge = $hen ? $hen->current_age_weeks . ' wks' : '—';
        }

        return response()->json([
            'cageCode'  => $d['cageCode'],
            'period'    => $d['period'],
            'isAll'     => $d['isAll'],
            'cageColor' => $cageColor,
            'kpi' => [
                'avgHdep'  => $d['avgHdep'],
                'bestDay'  => $d['bestDay'],
                'worstDay' => $d['worstDay'],
                'breed'    => $d['isAll'] ? 'Mixed' : $breed,
                'flockAge' => $d['isAll'] ? '—' : $flockAge,
            ],
            'charts' => [
                'logs'           => $logs,
                'feedLogs'       => $feedLogs,
                'hasLogs'        => $hasLogs,
                'hasFeedOverlap' => $hasFeedOverlap,
            ],
        ]);
    }
}
