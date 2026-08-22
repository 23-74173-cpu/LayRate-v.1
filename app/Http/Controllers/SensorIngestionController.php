<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use App\Models\ProductionLog;
use App\Models\SensorOccupancyReading;
use App\Services\EnvironmentAlertService;
use App\Services\ReportingDateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\DeviceHealthEvaluator;

class SensorIngestionController extends Controller
{
    /**
     * Accept sensor readings from an authenticated LAN device.
     *
     * Expected payload (JSON):
     * {
     *   "readings": [
     *     {
     *       "serial_number": "DHT22-001",
     *       "temperature_c": 28.90,
     *       "humidity_pct": 68.10
     *     },
     *     {
     *       "serial_number": "IRBBS-001",
     *       "count": 5
     *     }
     *   ],
     *   "recorded_at": "2026-07-09T10:30:00+08:00"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        app(DeviceHealthEvaluator::class)->registerDeviceHeartbeat($device);

        $data = $request->validate([
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.serial_number' => ['required', 'string', 'max:100'],
            'readings.*.temperature_c' => ['nullable', 'numeric'],
            'readings.*.humidity_pct' => ['nullable', 'numeric'],
            'readings.*.count' => ['nullable', 'integer', 'min:0'],
            'readings.*.relay_status' => ['nullable', 'string', Rule::in(['on', 'off'])],
            'readings.*.relay_safety' => ['nullable', 'boolean'],
            'recorded_at' => ['required', 'date'],
        ]);

        $recordedAt = $data['recorded_at'];
        $processed = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($data['readings'] as $index => $reading) {
                $serial = $reading['serial_number'];

                $hardwareItem = HardwareItem::findActiveForIngestion($serial, $device->id);

                if (! $hardwareItem) {
                    $errors[] = "Reading {$index}: serial number {$serial} is not registered to this device or is not active.";
                    continue;
                }

                if ($hardwareItem->device_type === 'DHT22') {
                    if (! isset($reading['temperature_c']) || ! isset($reading['humidity_pct'])) {
                        $errors[] = "Reading {$index}: DHT22 reading requires temperature_c and humidity_pct.";
                        continue;
                    }

                    // A real-time reading that (coincidentally) lands on the same
                    // recorded_at as a manual override must not clobber the
                    // override — mirror the egg-logging 'never overwrite manual'
                    // guard. Only possible on an exact noon-second collision.
                    $clobbersOverride = EnvironmentalLog::where('cage_id', $hardwareItem->cage_id)
                        ->where('recorded_at', $recordedAt)
                        ->where('is_override', 1)
                        ->exists();

                    if ($clobbersOverride) {
                        $errors[] = "Reading {$index}: timestamp collides with a manual override for cage {$hardwareItem->cage_id}; skipped.";
                        continue;
                    }

                    $envLog = EnvironmentalLog::updateOrCreate(
                        [
                            'cage_id' => $hardwareItem->cage_id,
                            'recorded_at' => $recordedAt,
                        ],
                        [
                            'temperature_c' => (float) $reading['temperature_c'],
                            'humidity_pct' => (float) $reading['humidity_pct'],
                        ]
                    );

                    EnvironmentAlertService::check($envLog);

                    app(DeviceHealthEvaluator::class)->registerDhtReading($hardwareItem, (float) $reading['temperature_c'], (float) $reading['humidity_pct']);

                    $processed[] = [
                        'serial_number' => $serial,
                        'type' => 'environment',
                        'cage_id' => $hardwareItem->cage_id,
                    ];
                } else            if ($hardwareItem->device_type === 'IR_breakbeam') {
                    if (! isset($reading['count'])) {
                        $errors[] = "Reading {$index}: IR breakbeam reading requires count.";
                        continue;
                    }

                    if (! $hardwareItem->cage_slot_id) {
                        $errors[] = "Reading {$index}: IR breakbeam sensor {$serial} is not assigned to a cage slot.";
                        continue;
                    }

                    $slot = $hardwareItem->cageSlot;
                    $reportedCount = (int) $reading['count'];
                    $actualOccupancy = $slot->current_occupancy;

                    /*
                     * DEBOUNCE — skip if the same count was already recorded
                     * within the last 5 seconds. The Arduino firmware already
                     * has a 1-second IR_COOLDOWN_MS, so 5 seconds on the
                     * server side catches any duplicate blocks sent by the
                     * Python bridge (e.g. due to curl retry or TCP re-send).
                     *
                     * This replaces the old rate-limit design which used a
                     * formula of 22 / max_chickens_per_slot hours (min 1h)
                     * — far too aggressive for live updates.
                     */
                    $lastReading = SensorOccupancyReading::where('hardware_item_id', $hardwareItem->id)
                        ->latest('recorded_at')
                        ->first();

                    if ($lastReading
                        && (int) $lastReading->reported_count === $reportedCount
                        && $lastReading->recorded_at->gt(now()->subSeconds(5))) {
                        continue;
                    }

                    // Health heartbeat BEFORE the write so the jump check sees
                    // the previous reading as its baseline (raw only).
                    app(DeviceHealthEvaluator::class)->registerIrReading($hardwareItem, $reportedCount, $slot);

                    SensorOccupancyReading::updateOrCreate(
                        [
                            'hardware_item_id' => $hardwareItem->id,
                            'recorded_at' => $recordedAt,
                        ],
                        [
                            'cage_slot_id' => $slot->id,
                            'reported_count' => $reportedCount,
                        ]
                    );

                    /*
                     * Auto-create/update a ProductionLog for today from the
                     * sensor reading so it appears in the egg logging UI.
                     * NEVER overwrites a manual entry — once a user has
                     * explicitly overridden the sensor via PIN/password,
                     * that value is authoritative until the user changes
                     * it again.  Only sensor-created logs or nonexistent
                     * logs are touched.
                     */
                    if ($slot->active_hen_count > 0) {
                        $logDate = now()->parse($recordedAt)->toDateString();
                        $existingLog = ProductionLog::where('cage_slot_id', $slot->id)
                            ->where('log_date', $logDate)
                            ->first();

                        // Never overwrite a manual entry — the user explicitly overrode the sensor
                        if ($existingLog && $existingLog->logged_via === 'manual') {
                            $errors[] = "Reading {$index}: slot {$slot->id} has a manual override for {$logDate}. Sensor reading skipped.";
                            continue;
                        }

                        /*
                         * INVARIANT GUARD — IR break-beam counts are
                         * physically monotonic within a day.  If an
                         * existing sensor-created log already records
                         * a HIGHER egg_count, the sensor likely reset
                         * (e.g. Arduino rebooted).  Do NOT overwrite.
                         */
                        if ($existingLog && $reportedCount < $existingLog->egg_count) {
                            logger()->warning('Sensor count regression detected', [
                                'cage_slot_id' => $slot->id,
                                'log_date' => $logDate,
                                'previous_count' => $existingLog->egg_count,
                                'reported_count' => $reportedCount,
                                'hardware_item_id' => $hardwareItem->id,
                                'serial_number' => $serial,
                            ]);

                            self::createSensorResetAlert($slot, $existingLog->egg_count, $reportedCount);

                            $errors[] = "Reading {$index}: count {$reportedCount} dropped from {$existingLog->egg_count} for slot {$slot->id} on {$logDate}. Sensor reset suspected.";
                            continue;
                        }

                        $henCount = $slot->active_hen_count;
                        $productionAttrs = [
                            'egg_count' => $reportedCount,
                            'hen_count' => $henCount,
                            'hdep' => $henCount > 0 ? round(($reportedCount / $henCount) * 100, 2) : 0,
                            'logged_via' => 'sensor',
                            'notes' => 'Sensor reading',
                        ];

                        // $existingLog (fetched above for the manual-override
                        // and regression checks) already IS the row
                        // ProductionLog::updateOrCreate() would look up again
                        // internally via the same (cage_slot_id, log_date)
                        // match — update it in place instead of re-querying.
                        if ($existingLog) {
                            $existingLog->update($productionAttrs);
                        } else {
                            ProductionLog::create($productionAttrs + [
                                'cage_slot_id' => $slot->id,
                                'log_date' => $logDate,
                            ]);
                        }
                    }

                    if ($reportedCount !== $actualOccupancy) {
                        self::createOccupancyMismatchAlert($slot, $reportedCount, $actualOccupancy);
                    }

                    $processed[] = [
                        'serial_number' => $serial,
                        'type' => 'occupancy',
                        'cage_slot_id' => $slot->id,
                        'mismatch' => $reportedCount !== $actualOccupancy,
                    ];
                } elseif ($hardwareItem->device_type === 'relay') {
                    if (! isset($reading['relay_status'])) {
                        $errors[] = "Reading {$index}: relay reading requires relay_status.";
                        continue;
                    }

                    $reportedStatus = $reading['relay_status'];
                    $reportedSafety = (bool) ($reading['relay_safety'] ?? false);

                    /*
                     * OVERRIDE RULE — do not repeat the silent-overwrite bug.
                     *
                     * When a user has manually set the relay (control_mode =
                     * manual) that value is the COMMANDED state and is
                     * authoritative until the user returns to AUTO — the same
                     * guarantee the ProductionLog `logged_via = 'manual'`
                     * guard gives egg counts. The bridge-reported status is
                     * accepted ONLY in auto mode.
                     *
                     * SAFETY DEFAULT — a safety-forced OFF (firmware reports
                     * "OFF (SAFETY)" when the DHT22 read is invalid) is NOT a
                     * normal manual-state confirmation and must NOT be applied
                     * to relay_status nor flip control_mode back to auto.
                     * Instead it is recorded in relay_safety so the UI can
                     * show "commanded ON, currently safety-blocked". Only when
                     * the command is already OFF is a safety report a no-op.
                     */
                    $wasOverride = $hardwareItem->control_mode === 'manual';

                    if ($wasOverride) {
                        $safetyBlocked = $reportedSafety && $hardwareItem->relay_status === 'on';

                        if ($safetyBlocked) {
                            logger()->warning('Relay safety-blocked: MANUAL ON but DHT22 invalid', [
                                'hardware_item_id' => $hardwareItem->id,
                                'serial_number' => $serial,
                                'commanded_status' => $hardwareItem->relay_status,
                            ]);
                        } else {
                            logger()->info('Relay reading skipped: manual override in effect', [
                                'hardware_item_id' => $hardwareItem->id,
                                'serial_number' => $serial,
                                'commanded_status' => $hardwareItem->relay_status,
                                'reported_status' => $reportedStatus,
                            ]);
                        }

                        $hardwareItem->update([
                            'relay_safety' => $safetyBlocked,
                            'relay_seen_at' => now(),
                        ]);
                    } else {
                        $hardwareItem->update([
                            'relay_status' => $reportedStatus,
                            'relay_safety' => false,
                            'relay_seen_at' => now(),
                        ]);
                    }

                    // Relay health: advisory only — never touches relay_safety.
                    app(DeviceHealthEvaluator::class)->registerRelaySeen($hardwareItem, $hardwareItem->cage?->latestEnvironmentLog);

                    $processed[] = [
                        'serial_number' => $serial,
                        'type' => 'relay',
                        'cage_id' => $hardwareItem->cage_id,
                        'relay_status' => $reportedStatus,
                        'relay_safety' => $reportedSafety,
                        'override_skipped' => $wasOverride,
                    ];
                } else {
                    $errors[] = "Reading {$index}: device type {$hardwareItem->device_type} is not ingestible.";
                }
            }

            if (count($processed) === 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No readings were accepted.',
                    'accepted' => 0,
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();

            $response = [
                'message' => 'Readings accepted.',
                'accepted' => count($processed),
                'processed' => $processed,
            ];

            if (! empty($errors)) {
                $response['errors'] = $errors;
            }

            $status = empty($errors) ? 200 : 207;

            return response()->json($response, $status);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'message' => 'Failed to process readings.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error.',
            ], 500);
        }
    }

    private static function createSensorResetAlert($slot, int $previousCount, int $reportedCount): void
    {
        $cage = $slot->cage;
        $cageCode = $cage?->cage_code ?? 'Unknown';

        [$dayStart, $dayEnd] = ReportingDateService::reportingDayWindow(ReportingDateService::reportingDateString());

        $exists = Alert::where('cage_id', $cage?->id)
            ->where('alert_type', 'sensor_reset')
            ->where('is_read', 0)
            ->where('triggered_at', '>=', $dayStart)
            ->where('triggered_at', '<', $dayEnd)
            ->exists();

        if ($exists) {
            return;
        }

        Alert::createDeduped([
            'cage_id' => $cage?->id,
            'alert_type' => 'sensor_reset',
            'message' => "IR sensor in {$cageCode} slot {$slot->row_number}-{$slot->column_number} likely reset: count dropped from {$previousCount} to {$reportedCount}",
            'is_read' => 0,
            'triggered_at' => now(),
            'dedup_key' => Alert::dedupKey($cage?->id, 'sensor_reset'),
            'alert_day' => ReportingDateService::reportingDateString(),
        ]);
    }

    private static function createOccupancyMismatchAlert($slot, int $reportedCount, int $actualOccupancy): void
    {
        $cage = $slot->cage;
        $cageCode = $cage?->cage_code ?? 'Unknown';
        [$dayStart, $dayEnd] = ReportingDateService::reportingDayWindow(ReportingDateService::reportingDateString());

        $exists = Alert::where('cage_id', $cage?->id)
            ->where('alert_type', 'occupancy_mismatch')
            ->where('is_read', 0)
            ->where('triggered_at', '>=', $dayStart)
            ->where('triggered_at', '<', $dayEnd)
            ->exists();

        if ($exists) {
            return;
        }

        Alert::createDeduped([
            'cage_id' => $cage?->id,
            'alert_type' => 'occupancy_mismatch',
            'message' => "Occupancy mismatch in {$cageCode} slot {$slot->row_number}-{$slot->column_number}: sensor reports {$reportedCount}, records show {$actualOccupancy}",
            'is_read' => 0,
            'triggered_at' => now(),
            'dedup_key' => Alert::dedupKey($cage?->id, 'occupancy_mismatch'),
            'alert_day' => ReportingDateService::reportingDateString(),
        ]);
    }
}
