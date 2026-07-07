<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EggSizeLog;
use App\Models\ProductionLog;
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

        // Timeline aggregation.
        $timeline = $this->timeline($groupBy);

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

    /**
     * Aggregate production logs by the requested period.
     */
    private function timeline(string $groupBy)
    {
        $select = match ($groupBy) {
            'week' => "DATE_FORMAT(log_date, '%Y-%u') as period, MIN(log_date) as period_start, COUNT(*) as records",
            'month' => "DATE_FORMAT(log_date, '%Y-%m') as period, MIN(log_date) as period_start, COUNT(*) as records",
            default => "log_date as period, log_date as period_start, COUNT(*) as records",
        };

        $query = ProductionLog::query()
            ->selectRaw("{$select}, SUM(egg_count) as total_eggs")
            ->groupBy('period')
            ->orderByDesc('period_start');

        return $query->get()->map(fn ($row) => [
            'period' => $row->period,
            'period_start' => $row->period_start,
            'total_eggs' => (int) $row->total_eggs,
            'records' => (int) $row->records,
            'label' => $this->periodLabel($row->period, $groupBy),
        ]);
    }

    private function periodLabel(string $period, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => 'Week ' . substr($period, 5) . ', ' . substr($period, 0, 4),
            'month' => \Carbon\Carbon::parse($period . '-01')->format('F Y'),
            default => \Carbon\Carbon::parse($period)->format('M j, Y'),
        };
    }
}
