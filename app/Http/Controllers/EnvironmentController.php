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
use Illuminate\Support\Facades\Cache;
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
        $cages = Cage::orderBy('cage_code')->get();

        // Latest REAL reading per cage (see EnvironmentalLog::latestRealPerCage
        // for why this is an explicit query, not a constrained latestOfMany).
        // Set as the loaded relation so every read below sees real-only data.
        $latestReal = EnvironmentalLog::latestRealPerCage($cages->pluck('id'));
        $cages->each(fn ($cage) => $cage->setRelation(
            'latestEnvironmentLog', $latestReal->get($cage->id)
        ));

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
            ->where('is_demo', false)
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

            // Source label: a reading written by hand (or imported as such) is a
            // manual log; only raw is_override=0 rows come from actual sensors.
            $source = $env->is_override ? 'Manual Log' : 'Sensor';

            return (object) compact('env', 'tempStatus', 'humStatus', 'status', 'source', 'cage');
        })->filter();

        // Cages with an assigned, active DHT22 sensor. Cards render live
        // readings ONLY for these cages — a cage with no sensor must not
        // show temp/humidity nor a "Sensor" badge (env rows there can only
        // be manual entries, and labeling them "Sensor" is wrong).
        $sensorCageIds = HardwareItem::where('device_type', 'DHT22')
            ->where('status', 'active')
            ->whereNotNull('cage_id')
            ->distinct()
            ->pluck('cage_id');

        // "Active sensors" = cages currently fed by a real sensor reading, not by
        // manual overrides. This is what the top metric should report — before
        // this fix it counted every cage with any env row (all 3 here), even
        // though hardware_items is empty.
        $activeSensors = $latestPerCage
            ->filter(fn ($r) => $r->source === 'Sensor' && $sensorCageIds->contains($r->cage->id))
            ->count();

        // Readings shown on per-cage cards: sensor-assigned cages only.
        // (Coop-wide KPIs/trends below still aggregate all rows.)
        $sensorReadings = $latestPerCage
            ->filter(fn ($r) => $sensorCageIds->contains($r->cage->id))
            ->values();

        $trendData = EnvironmentalLog::select(
                DB::raw("DATE_FORMAT(recorded_at, '{$dateFormat}') as period"),
                'cage_id',
                DB::raw('ROUND(AVG(temperature_c),1) as avg_temp'),
                DB::raw('ROUND(AVG(humidity_pct),1) as avg_hum')
            )
            ->where('is_demo', false)
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
            ->where('is_demo', false)
            ->where('recorded_at', '>=', $since)
            ->groupBy('time_slot')
            ->orderByDesc('time_slot')
            ->limit(10)
            ->get();

        $avgTemp = $latestPerCage->avg(fn($r) => $r->env->temperature_c);
        $avgHum  = $latestPerCage->avg(fn($r) => $r->env->humidity_pct);

        $tempValues = $latestPerCage->pluck('env.temperature_c');
        $humValues  = $latestPerCage->pluck('env.humidity_pct');

        $avgStatus = EnvironmentStatusService::summary((float) $avgTemp, (float) $avgHum, $thresholds);

        return view('environment._live-data', compact(
            'cages', 'latestPerCage', 'sensorCageIds', 'sensorReadings', 'activeSensors', 'trendData', 'summaryLogs',
            'avgTemp', 'avgHum', 'avgStatus',
            'tempValues', 'humValues',
            'thresholds', 'range'
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
            ->where('is_demo', false)
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

    /**
     * Manual live reading entry from the Environment page. Stores an override
     * reading for the selected cage (or all cages when cage_id === 'all') at
     * the current reporting day's noon, so it takes precedence over sensor
     * readings in live data.
     */
    public function storeManual(Request $request)
    {
        $allCageIds = Cage::orderBy('cage_code')->pluck('id')->all();

        $validated = $request->validate([
            'cage_id'        => ['required', Rule::in(array_merge($allCageIds, ['all']))],
            'temperature_c'  => 'required|numeric|min:-10|max:60',
            'humidity_pct'   => 'required|numeric|min:0|max:100',
        ]);

        // 'all' writes the same reading to every cage; otherwise a single cage.
        $cageIds = $validated['cage_id'] === 'all'
            ? $allCageIds
            : [(int) $validated['cage_id']];

        $noon = ReportingDateService::reportingDayStart()->copy()->addHours(12);
        [$repStart, $repEnd] = ReportingDateService::reportingDayWindow(
            ReportingDateService::reportingDateString()
        );

        // Replace any existing override for each target cage on the current
        // reporting day so the manual entry is the authoritative reading.
        EnvironmentalLog::whereIn('cage_id', $cageIds)
            ->where('is_override', 1)
            ->whereBetween('recorded_at', [$repStart, $repEnd])
            ->delete();

        foreach ($cageIds as $cageId) {
            EnvironmentalLog::create([
                'cage_id'        => $cageId,
                'recorded_at'    => $noon,
                'temperature_c'  => $validated['temperature_c'],
                'humidity_pct'   => $validated['humidity_pct'],
                'is_override'    => true,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('environment')->with('success', 'Manual reading saved.');
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

        // Bust the ingestion lookup cache so the next sensor POST (arrives
        // within ~1s, well before the bridge's 2s command poll) resolves this
        // relay fresh via HardwareItem::findActiveForIngestion() instead of
        // reading a up-to-300s-stale model whose outdated control_mode would
        // make SensorIngestionController overwrite the command just written
        // here. Same Cache::forget pattern as HardwareItemController.
        $this->forgetIngestionCache($relay->serial_number, $relay->device_id);

        $relay->refresh();
        $payload = RelayStateService::payload($relay);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'relay' => $payload]);
        }

        return back()->with('success', 'Relay set to ' . strtoupper($action) . '.');
    }

    private function forgetIngestionCache(string $serialNumber, ?int $deviceId): void
    {
        if ($deviceId === null) {
            return;
        }

        Cache::forget(HardwareItem::ingestionCacheKey($serialNumber, $deviceId));
    }
}

