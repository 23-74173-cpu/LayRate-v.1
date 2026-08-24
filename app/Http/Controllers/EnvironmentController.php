<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use App\Models\Setting;
use App\Services\EnvironmentStatusService;
use App\Services\RelayStateService;
use App\Services\ReportingDateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EnvironmentController extends Controller
{
    public function index(Request $request)
    {
        $thresholds = Setting::thresholds();
        $envTab = $request->query('envTab', 'live');
        $cages = \App\Models\Cage::orderBy('cage_code')->pluck('cage_code', 'id');

        $relay = $this->activeRelay();
        $relayState = RelayStateService::payload($relay);

        return view('environment', compact('thresholds', 'envTab', 'cages', 'relay', 'relayState'));
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

        // Manual overrides on the current reporting day are preferred over the
        // newest raw reading (see updateLog). Reporting-day window (Asia/Manila,
        // 06:00 reset) matches the app's date convention (see Prompt 2 fix).
        [$repStart, $repEnd] = ReportingDateService::reportingDayWindow(ReportingDateService::reportingDateString());
        $overrideByCage = EnvironmentalLog::where('is_override', 1)
            ->whereBetween('recorded_at', [$repStart, $repEnd])
            ->get()
            ->keyBy('cage_id');

        $latestPerCage = $cages->map(function ($cage) use ($thresholds, $overrideByCage) {
            // A manual override for the current reporting day is authoritative
            // over the newest live reading. Past-day overrides only affect
            // that day's average, not "current".
            $env = $overrideByCage[$cage->id] ?? $cage->latestEnvironmentLog;
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
                cage_id,
                DATE(recorded_at) as log_date,
                CASE WHEN MAX(is_override) = 1 THEN MAX(CASE WHEN is_override = 1 THEN temperature_c END)
                     ELSE ROUND(AVG(temperature_c), 1) END as avg_temp,
                CASE WHEN MAX(is_override) = 1 THEN MAX(CASE WHEN is_override = 1 THEN humidity_pct END)
                     ELSE ROUND(AVG(humidity_pct), 0) END as avg_hum,
                CASE WHEN MAX(is_override) = 1 THEN MAX(CASE WHEN is_override = 1 THEN temperature_c END)
                     ELSE ROUND(MIN(temperature_c), 1) END as min_temp,
                CASE WHEN MAX(is_override) = 1 THEN MAX(CASE WHEN is_override = 1 THEN temperature_c END)
                     ELSE ROUND(MAX(temperature_c), 1) END as max_temp,
                CASE WHEN MAX(is_override) = 1 THEN MAX(CASE WHEN is_override = 1 THEN humidity_pct END)
                     ELSE ROUND(MIN(humidity_pct), 0) END as min_hum,
                CASE WHEN MAX(is_override) = 1 THEN MAX(CASE WHEN is_override = 1 THEN humidity_pct END)
                     ELSE ROUND(MAX(humidity_pct), 0) END as max_hum,
                CASE WHEN MAX(is_override) = 1 THEN 1 ELSE COUNT(*) END as reading_count
            ")
            ->groupBy('cage_id', 'log_date')
            ->orderByDesc('log_date')
            ->orderBy('cage_id');

        if ($request->filled('date_from')) {
            $query->where('recorded_at', '>=', $request->date_from);
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

    public function updateLog(Request $request, int $cageId, string $date)
    {
        $validated = $request->validate([
            'temperature_c' => 'required|numeric|min:-10|max:60',
            'humidity_pct' => 'required|numeric|min:0|max:100',
        ]);

        $dateStart = \Carbon\Carbon::parse($date)->startOfDay();
        $dateEnd = \Carbon\Carbon::parse($date)->endOfDay();
        $noon = $dateStart->copy()->setHour(12);

        // Delete all raw readings for this cage/date so the override row
        // is the only row — the on-the-fly AVG in logs() and the nightly
        // aggregation will both produce the override value.
        EnvironmentalLog::where('cage_id', $cageId)
            ->whereBetween('recorded_at', [$dateStart, $dateEnd])
            ->delete();

        EnvironmentalLog::create([
            'cage_id' => $cageId,
            'recorded_at' => $noon,
            'temperature_c' => $validated['temperature_c'],
            'humidity_pct' => $validated['humidity_pct'],
            'is_override' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('environment', ['envTab' => 'logs'])
            ->with('success', "Environment log for Cage #{$cageId} on {$date} overridden.");
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

    private function activeRelay(): ?HardwareItem
    {
        return HardwareItem::with('lastChangedBy')
            ->where('device_type', 'relay')
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }

    /**
     * Manual relay control: POST action=on|off|auto.
     *
     * Turn-off semantics: on/off set control_mode=manual (authoritative until
     * the user explicitly returns to auto); auto hands control back to the
     * firmware hysteresis loop. The bridge picks the new state up on its next
     * poll of the command endpoint.
     */
    public function controlRelay(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['on', 'off', 'auto'])],
        ]);
        $action = $validated['action'];

        $relay = $this->activeRelay();

        if (! $relay) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active relay device is registered.',
                ], 404);
            }

            return back()->with('error', 'No active relay device is registered.');
        }

        if ($action === 'auto') {
            $relay->update([
                'control_mode' => 'auto',
                'relay_safety' => false,
                'last_changed_at' => now(),
                'last_changed_by' => $request->user()?->id,
            ]);
        } else {
            $relay->update([
                'control_mode' => 'manual',
                'relay_status' => $action,
                'relay_safety' => false,
                'last_changed_at' => now(),
                'last_changed_by' => $request->user()?->id,
            ]);
        }

        $relay->refresh();
        $payload = RelayStateService::payload($relay);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'relay' => $payload]);
        }

        return back()->with('success', 'Relay set to ' . strtoupper($action) . '.');
    }
}

