<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EggSizeLog;
use App\Models\ProductionLog;
use App\Services\ProductionTimelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class EggProductionHistoryController extends Controller
{
    public function index(Request $request)
    {
        $groupBy = $request->query('group_by', 'day');
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        // Lifetime total — single source of truth, reused by dashboard KPI.
        // Orphaned logs (cage_slot_id NULL, from deleted cages whose history
        // was preserved) are excluded here and in the timeline below so the
        // total stays consistent with the dashboard and per-cage breakdown.
        $lifetimeEggs = ProductionLog::whereNotNull('cage_slot_id')->sum('egg_count');

        // Timeline aggregation via shared service.
        $timeline = ProductionTimelineService::aggregate($groupBy);

        // Records count must reflect the whole timeline, not just one page.
        $timelineRecordsTotal = $timeline->sum('records');

        // Cumulative total is a running sum across the full (descending) dataset —
        // pre-computed here, before pagination, so page 2+ doesn't restart the count.
        $cumulative = $lifetimeEggs;
        $timeline = $timeline->map(function ($row) use (&$cumulative) {
            $row['cumulative'] = $cumulative;
            $cumulative -= $row['total_eggs'];
            return $row;
        });

        $timeline = $this->paginateCollection($timeline, $request);

        // Breakdown by cage.
        $byCage = Cage::where('is_active', 1)
            ->orderBy('cage_code')
            ->get()
            ->map(fn ($cage) => [
                'cage_code' => $cage->cage_code,
                'color' => $cage->color,
                'total_eggs' => (int) $cage->productionLogs->sum('egg_count'),
            ])
            ->filter(fn ($c) => $c['total_eggs'] > 0)
            ->values();

        // Breakdown by size using EggSizeLog as source of truth.
        $bySize = EggSizeLog::select('egg_size', DB::raw('SUM(count) as total'))
            ->groupBy('egg_size')
            ->orderBy('egg_size')
            ->get()
            ->map(fn ($row) => [
                'size' => $row->egg_size,
                'total' => (int) $row->total,
            ]);

        return view('egg-production-history', compact(
            'lifetimeEggs', 'timeline', 'timelineRecordsTotal', 'byCage', 'bySize', 'groupBy'
        ));
    }

    public function productionHistory()
    {
        return view('eggs.production-history', ['activeTab' => 'production-history']);
    }

    private function paginateCollection($items, Request $request, int $perPage = 5): LengthAwarePaginator
    {
        $page  = LengthAwarePaginator::resolveCurrentPage();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($slice, $items->count(), $perPage, $page, [
            'path'  => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);
    }
}
