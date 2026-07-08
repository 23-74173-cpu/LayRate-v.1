<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EggSizeLog;
use App\Models\ProductionLog;
use App\Services\ProductionTimelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EggProductionHistoryController extends Controller
{
    public function index(Request $request)
    {
        $groupBy = $request->query('group_by', 'day');
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        // Lifetime total — single source of truth, reused by dashboard KPI.
        $lifetimeEggs = ProductionLog::sum('egg_count');

        // Timeline aggregation via shared service.
        $timeline = ProductionTimelineService::aggregate($groupBy);

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
            'lifetimeEggs', 'timeline', 'byCage', 'bySize', 'groupBy'
        ));
    }
}
