<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\Setting;
use App\Services\EnvironmentStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnvironmentController extends Controller
{
    public function index(Request $request)
    {
        $thresholds = Setting::thresholds();
        $envTab = $request->query('envTab', 'live');

        return view('environment', compact('thresholds', 'envTab'));
    }

    public function liveData(Request $request)
    {
        $thresholds = Setting::thresholds();
        $cages = Cage::with(['latestEnvironmentLog'])->orderBy('cage_code')->get();

        $range = $request->query('range', '24h');

        $trendDateFormats = [
            '24h' => ["%H:00", now()->subHours(24)],
            'week' => ["%a %d", now()->subWeek()],
            'month' => ["%b %d", now()->subMonth()],
        ];
        [$dateFormat, $since] = $trendDateFormats[$range] ?? $trendDateFormats['24h'];

        $latestPerCage = $cages->map(function ($cage) use ($thresholds) {
            $env = $cage->latestEnvironmentLog;
            if (! $env) return null;

            $tempStatus = EnvironmentStatusService::tempStatus($env->temperature_c, $thresholds);
            $humStatus = EnvironmentStatusService::humStatus($env->humidity_pct, $thresholds);

            $status = 'Normal';
            if ($tempStatus === 'Alert' || $humStatus === 'Alert') $status = 'Alert';
            elseif ($tempStatus === 'Watch' || $humStatus === 'Watch') $status = 'Watch';

            return (object) compact('env', 'tempStatus', 'humStatus', 'status', 'cage');
        })->filter();

        $trendData = EnvironmentalLog::select(
                DB::raw("DATE_FORMAT(recorded_at, '{$dateFormat}') as period"),
                'cage_id',
                DB::raw('ROUND(AVG(temperature_c),1) as avg_temp'),
                DB::raw('ROUND(AVG(humidity_pct),1) as avg_hum')
            )
            ->where('recorded_at', '>=', $since)
            ->groupBy('period', 'cage_id')
            ->orderBy('period')
            ->get()
            ->groupBy('cage_id');

        $summaryLogs = EnvironmentalLog::select(
                DB::raw("DATE_FORMAT(recorded_at, '{$dateFormat}') as time_slot"),
                DB::raw('ROUND(AVG(temperature_c),1) as avg_temp'),
                DB::raw('ROUND(AVG(humidity_pct),1) as avg_hum')
            )
            ->where('recorded_at', '>=', $since)
            ->groupBy('time_slot')
            ->orderByDesc('time_slot')
            ->limit(10)
            ->get();

        $avgTemp = $latestPerCage->avg(fn($r) => $r->env->temperature_c);
        $avgHum  = $latestPerCage->avg(fn($r) => $r->env->humidity_pct);

        return view('environment._live-data', compact(
            'cages', 'latestPerCage', 'trendData', 'summaryLogs',
            'avgTemp', 'avgHum', 'thresholds', 'range'
        ));
    }

    public function logs(Request $request)
    {
        $thresholds = Setting::thresholds();
        $cages = Cage::orderBy('cage_code')->pluck('cage_code', 'id');

        $query = EnvironmentalLog::selectRaw("
                DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00') as time_slot,
                ROUND(AVG(temperature_c), 1) as avg_temp,
                ROUND(AVG(humidity_pct), 0) as avg_hum
            ")
            ->groupBy('time_slot')
            ->orderByDesc('time_slot');

        if ($request->filled('date_from')) {
            $query->where('recorded_at', '>=', $request->date_from);
        } else {
            $query->where('recorded_at', '>=', now()->subHours(24));
        }

        if ($request->filled('date_to')) {
            $query->where('recorded_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('cage_id')) {
            $query->where('cage_id', $request->cage_id);
        }

        $summaryLogs = $query->paginate(20);

        return view('environment._logs', compact('summaryLogs', 'thresholds', 'cages'));
    }

    public function saveThresholds(Request $request)
    {
        $data = $request->validate([
            'temp_min' => 'required|numeric|min:0|max:50',
            'temp_max' => 'required|numeric|min:0|max:50|gte:temp_min',
            'hum_min'  => 'required|numeric|min:0|max:100',
            'hum_max'  => 'required|numeric|min:0|max:100|gte:hum_min',
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('environment')
            ->with('success', 'Thresholds saved.');
    }
}

