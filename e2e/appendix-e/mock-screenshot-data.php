<?php

// MOCK data for the Appendix E screenshots (Notes, Environment, Hardware).
// Local development database only. Everything it writes is marked so it can be
// removed exactly:
//   hardware_items listed (by id) in mock-screenshot-data.json; serials
//     start MOCK- but may have been renamed in the UI since
//   environmental_logs.provenance = 'mock_screenshot'   (today only, so no
//     forecast input day is touched: forecasts read days before today)
//   notes listed in mock-screenshot-data.json next to this script
//
//   production_logs.created_at of the last 7 days' imported rows (the
//     "Eggs Collected by Time" entry times), originals kept in the json
//
// Usage:  php e2e/appendix-e/mock-screenshot-data.php --add | --add-collection-times | --remove

use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use App\Models\Note;
use App\Services\ReportingDateService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$manifest = __DIR__.'/mock-screenshot-data.json';
$mode = $argv[1] ?? '';

$db = DB::connection()->getDatabaseName();
if (! app()->environment('local') || $db !== 'layrate' || ! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "Refusing: local layrate database only (got {$db}, ".app()->environment().")\n");
    exit(2);
}

if ($mode === '--add-collection-times') {
    $data = is_file($manifest) ? json_decode(file_get_contents($manifest), true) : [];
    if (! empty($data['production_created_at'])) {
        fwrite(STDERR, "Collection times already set; run --remove first.\n");
        exit(1);
    }
    // Each cage is logged after its morning collection, at a slightly
    // different time each day; now and then collection runs late, and
    // Sundays start later. Seeded so a rerun gives the same times.
    mt_srand(20261115);
    $now = ReportingDateService::now();
    $base = ['CAGE-A' => 7 * 60 + 30, 'CAGE-B' => 8 * 60 + 15, 'CAGE-C' => 9 * 60];
    $original = [];
    DB::transaction(function () use ($now, $base, &$original) {
        $rows = DB::table('production_logs')
            ->where('log_date', '>=', $now->copy()->subDays(6)->toDateString())
            ->where('log_date', '<=', $now->toDateString())
            ->where('source_key', 'like', 'simulated_extension:%:production')
            ->orderBy('log_date')->orderBy('source_key')->get(['id', 'log_date', 'source_key', 'created_at']);
        foreach ($rows as $row) {
            $code = explode(':', $row->source_key)[2];
            $day = \Carbon\Carbon::parse(substr((string) $row->log_date, 0, 10), ReportingDateService::timezone());
            $minutes = $base[$code] + mt_rand(-40, 40) + ($day->isSunday() ? 60 : 0);
            if (mt_rand(1, 100) <= 15) {
                $minutes = mt_rand(10 * 60 + 30, 13 * 60 + 30); // late collection
            }
            $at = $day->copy()->addMinutes($minutes)->addSeconds(mt_rand(0, 59));
            if ($at->gt($now)) {
                $at = $now->copy()->subMinutes(mt_rand(3, 25));
            }
            $original[$row->id] = (string) $row->created_at;
            DB::table('production_logs')->where('id', $row->id)->update(['created_at' => $at->format('Y-m-d H:i:s')]);
        }
    });
    $data['production_created_at'] = $original;
    file_put_contents($manifest, json_encode($data, JSON_PRETTY_PRINT));
    echo 'Set collection times on '.count($original)." production rows\n";
    exit(0);
}

if ($mode === '--remove') {
    DB::transaction(function () use ($manifest) {
        $times = is_file($manifest) ? (json_decode(file_get_contents($manifest), true)['production_created_at'] ?? []) : [];
        foreach ($times as $id => $createdAt) {
            DB::table('production_logs')->where('id', $id)->update(['created_at' => $createdAt]);
        }
        $noteIds = is_file($manifest) ? (json_decode(file_get_contents($manifest), true)['note_ids'] ?? []) : [];
        $n = Note::whereIn('id', $noteIds)->delete();
        $e = EnvironmentalLog::where('provenance', 'mock_screenshot')->delete();
        $hardwareIds = is_file($manifest) ? (json_decode(file_get_contents($manifest), true)['hardware_ids'] ?? []) : [];
        $h = HardwareItem::whereIn('id', $hardwareIds)->orWhere('serial_number', 'like', 'MOCK-%')->delete();
        echo "Removed: {$n} notes, {$e} environmental readings, {$h} hardware items; restored ".count($times)." collection times\n";
    });
    @unlink($manifest);
    exit(0);
}

if ($mode !== '--add') {
    fwrite(STDERR, "Usage: --add | --add-collection-times | --remove\n");
    exit(2);
}
if (HardwareItem::where('serial_number', 'like', 'MOCK-%')->exists() || EnvironmentalLog::where('provenance', 'mock_screenshot')->exists() || is_file($manifest)) {
    fwrite(STDERR, "Mock data already present; run --remove first.\n");
    exit(1);
}

$cages = Cage::whereIn('cage_code', ['CAGE-A', 'CAGE-B', 'CAGE-C'])->orderBy('cage_code')->get()->keyBy('cage_code');
$now = ReportingDateService::now();
// Readings are stored in UTC and the forecast matches DATE(recorded_at) to a
// production day, so start no earlier than today's UTC midnight: no reading
// may fall on yesterday's (UTC) date, which is a forecast input day.
$dayStart = max($now->copy()->startOfDay(), $now->copy()->utc()->startOfDay()->setTimezone(ReportingDateService::timezone()));

$result = DB::transaction(function () use ($cages, $now, $dayStart) {
    $hardware = [];
    foreach ($cages as $code => $cage) {
        $hardware[] = HardwareItem::create([
            'device_type' => 'DHT22', 'serial_number' => "MOCK-DHT22-{$code}", 'cage_id' => $cage->id,
            'installation_date' => '2026-11-01', 'last_calibration_date' => '2026-11-01', 'status' => 'active',
            'health_state' => 'online', 'last_valid_reading_at' => $now->copy()->setTimezone(config('app.timezone')),
        ])->id;
    }
    // IR break-beam counters on the first four CAGE-A slots (slot only, as the
    // app's rules require); one marked faulty.
    foreach ($cages['CAGE-A']->cageSlots()->orderBy('slot_number')->take(4)->get() as $i => $slot) {
        $hardware[] = HardwareItem::create([
            'device_type' => 'IR_breakbeam', 'serial_number' => sprintf('MOCK-IR-A%02d', $slot->slot_number),
            'cage_slot_id' => $slot->id, 'installation_date' => '2026-11-01',
            'status' => $i === 3 ? 'faulty' : 'active', 'health_state' => $i === 3 ? 'faulty' : 'online',
            'fault_issue' => $i === 3 ? 'Beam misaligned (mock)' : null,
        ])->id;
    }
    // Relays are assigned to a cage.
    $hardware[] = HardwareItem::create([
        'device_type' => 'relay', 'serial_number' => 'MOCK-RELAY-FAN-01', 'cage_id' => $cages['CAGE-A']->id,
        'installation_date' => '2026-11-01', 'status' => 'active', 'health_state' => 'online',
        'relay_status' => 'off', 'control_mode' => 'auto',
    ])->id;

    // Today's readings every 15 minutes per cage: a gentle day curve.
    $readings = 0;
    $offsets = ['CAGE-A' => 0.0, 'CAGE-B' => 0.4, 'CAGE-C' => -0.3];
    for ($t = $dayStart->copy(); $t->lte($now); $t->addMinutes(15)) {
        $hour = $t->hour + $t->minute / 60;
        $warmth = sin(($hour - 8) / 24 * 2 * M_PI); // peak mid-afternoon
        foreach ($cages as $code => $cage) {
            EnvironmentalLog::create([
                'cage_id' => $cage->id,
                'recorded_at' => $t->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                'temperature_c' => round(27.5 + 2.5 * $warmth + $offsets[$code], 1),
                'humidity_pct' => round(78 - 6 * $warmth - $offsets[$code], 1),
                'is_override' => false, 'is_demo' => false, 'provenance' => 'mock_screenshot',
            ]);
            $readings++;
        }
    }

    $notes = [];
    foreach ([
        ['Production', 'CAGE-A', 'Egg count dipped slightly after the afternoon heat; keep an eye on CAGE-A tomorrow.'],
        ['Feed', null, 'New layer feed delivery expected Monday. Check crude protein on the bag label before use.'],
        ['Environment', 'CAGE-B', 'Fan relay switched to AUTO; humidity stayed above 80% before sunrise.'],
        ['Hens', 'CAGE-C', 'Two hens in CAGE-C slot 12 look pale. Monitor feed intake and isolate if it continues.'],
        ['Mortality', 'CAGE-A', 'One mortality in CAGE-A: no visible injury, cause recorded as Unknown.'],
        ['General', null, 'Cleaned water lines and checked all nipple drinkers in every cage.'],
        ['Reports', null, 'Weekly production report shared with the farm owner.'],
    ] as [$category, $code, $body]) {
        $notes[] = Note::create(['category' => $category, 'cage_id' => $code ? $cages[$code]->id : null, 'body' => $body])->id;
    }

    return compact('hardware', 'readings', 'notes');
});

file_put_contents($manifest, json_encode(['note_ids' => $result['notes'], 'hardware_ids' => $result['hardware'], 'added_at' => $now->toDateTimeString()], JSON_PRETTY_PRINT));
echo "Added: ".count($result['hardware'])." hardware items, {$result['readings']} environmental readings (today only), ".count($result['notes'])." notes\n";
