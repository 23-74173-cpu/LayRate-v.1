<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\FeedConsumptionLog;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private function cageOrAll(Request $request): array
    {
        $period   = $request->get('period', 'week');

        $allCages = Cage::orderBy('cage_code')->get();
        if ($allCages->isEmpty()) {
            return ['redirect' => redirect()->route('dashboard')->with('error', 'No cages configured. Create a cage first.')];
        }

        $cageCode = $request->get('cage', 'all');

        $days = match($period) {
            'month'   => 30,
            '3months' => 90,
            default   => 7,
        };

        $isAll = $cageCode === 'all';

        if ($isAll) {
            $cageIds = $allCages->pluck('id');
            $logs = ProductionLog::whereIn('cage_slot_id', function ($q) use ($cageIds) {
                $q->select('id')->from('cage_slots')->whereIn('cage_id', $cageIds);
            })
                ->where('log_date', '>=', now()->subDays($days))
                ->orderBy('log_date')
                ->get();

            $feedLogs = FeedConsumptionLog::whereIn('cage_id', $cageIds)
                ->where('log_date', '>=', now()->subDays($days))
                ->orderBy('log_date')
                ->get();

            $totalHens = $allCages->sum('total_capacity');

            $cage = null;
        } else {
            $cage = Cage::with(['hens' => fn($q) => $q->where('is_active', 1)])
                ->where('cage_code', $cageCode)
                ->firstOrFail();

            $logs = $cage->productionLogs()
                ->where('log_date', '>=', now()->subDays($days))
                ->orderBy('log_date')
                ->get();

            $feedLogs = FeedConsumptionLog::where('cage_id', $cage->id)
                ->where('log_date', '>=', now()->subDays($days))
                ->orderBy('log_date')
                ->get();
        }

        $avgHdep  = $logs->count() ? round($logs->avg('hdep'), 1) : '-';
        $bestDay  = $logs->count() ? round($logs->max('hdep'), 1) : '-';
        $worstDay = $logs->count() ? round($logs->min('hdep'), 1) : '-';

        return compact('cage', 'cageCode', 'period', 'logs', 'feedLogs', 'avgHdep', 'bestDay', 'worstDay', 'allCages', 'isAll');
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

        return view('analytics._charts', $data);
    }

    public function data(Request $request)
    {
        $d = $this->cageOrAll($request);
        if (isset($d['redirect'])) return response()->json(['error' => 'No cages configured'], 422);

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
