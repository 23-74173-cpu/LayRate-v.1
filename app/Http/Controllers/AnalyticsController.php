<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\FeedConsumptionLog;
use App\Models\ProductionLog;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $cageCode = $request->get('cage', 'CAGE-A');
        $period   = $request->get('period', 'week');

        $cage = Cage::with([
            'hens' => fn($q) => $q->where('is_active', 1),
        ])->where('cage_code', $cageCode)->firstOrFail();

        $days = match($period) {
            'month'   => 30,
            '3months' => 90,
            default   => 7,
        };

        $logs = ProductionLog::where('cage_id', $cage->id)
            ->where('log_date', '>=', now()->subDays($days))
            ->orderBy('log_date')
            ->get();

        $feedLogs = FeedConsumptionLog::where('cage_id', $cage->id)
            ->where('log_date', '>=', now()->subDays($days))
            ->orderBy('log_date')
            ->get();

        $avgHdep  = $logs->count() ? round($logs->avg('hdep'), 1) : 0;
        $bestDay  = $logs->count() ? round($logs->max('hdep'), 1) : 0;
        $worstDay = $logs->count() ? round($logs->min('hdep'), 1) : 0;

        $allCages = Cage::orderBy('cage_code')->get();

        return view('analytics', compact(
            'cage', 'cageCode', 'period', 'logs', 'feedLogs',
            'avgHdep', 'bestDay', 'worstDay', 'allCages'
        ));
    }
}
