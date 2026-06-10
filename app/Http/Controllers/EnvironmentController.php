<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnvironmentController extends Controller
{
    public function index()
    {
        $thresholds = Setting::thresholds();
        $cages = Cage::with(['latestEnvironment'])->orderBy('cage_code')->get();

        $latestPerCage = $cages->map(function ($cage) use ($thresholds) {
            $env = $cage->latestEnvironment;
            if (! $env) return null;

            $tempStatus = $this->tempStatus($env->temperature_c, $thresholds);
            $humStatus  = $this->humStatus($env->humidity_pct, $thresholds);

            $status = 'Normal';
            if ($tempStatus === 'Alert' || $humStatus === 'Alert') $status = 'Alert';
            elseif ($tempStatus === 'Watch' || $humStatus === 'Watch') $status = 'Watch';

            return (object) compact('env', 'tempStatus', 'humStatus', 'status', 'cage');
        })->filter();

        $trendData = EnvironmentalLog::select(
                DB::raw("DATE_FORMAT(recorded_at, '%H:00') as hour"),
                'cage_id',
                DB::raw('ROUND(AVG(temperature_c),1) as avg_temp'),
                DB::raw('ROUND(AVG(humidity_pct),1) as avg_hum')
            )
            ->where('recorded_at', '>=', now()->subHours(24))
            ->groupBy('hour', 'cage_id')
            ->orderBy('hour')
            ->get()
            ->groupBy('cage_id');

        $summaryLogs = EnvironmentalLog::select(
                DB::raw("DATE_FORMAT(recorded_at, '%H:00') as time_slot"),
                DB::raw('ROUND(AVG(temperature_c),1) as avg_temp'),
                DB::raw('ROUND(AVG(humidity_pct),1) as avg_hum')
            )
            ->where('recorded_at', '>=', now()->subHours(24))
            ->groupBy('time_slot')
            ->orderByDesc('time_slot')
            ->limit(10)
            ->get();

        $avgTemp = $latestPerCage->avg(fn($r) => $r->env->temperature_c);
        $avgHum  = $latestPerCage->avg(fn($r) => $r->env->humidity_pct);

        return view('environment', compact(
            'cages', 'latestPerCage', 'trendData', 'summaryLogs',
            'avgTemp', 'avgHum', 'thresholds'
        ));
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

        return redirect()->route('environment')
            ->with('success', 'Thresholds saved.');
    }

    private function tempStatus(float $temp, array $t): string
    {
        if ($temp > $t['temp_max']) return 'Alert';
        if ($temp > $t['temp_max'] - 1.5) return 'Watch';
        return 'OK';
    }

    private function humStatus(float $hum, array $t): string
    {
        if ($hum > $t['hum_max']) return 'Alert';
        if ($hum >= $t['hum_max']) return 'Watch';
        return 'OK';
    }
}
