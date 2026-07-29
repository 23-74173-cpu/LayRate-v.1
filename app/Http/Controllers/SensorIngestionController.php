<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use App\Models\ProductionLog;
use App\Models\SensorOccupancyReading;
use App\Services\EnvironmentAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        $data = $request->validate([
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.serial_number' => ['required', 'string', 'max:100'],
            'readings.*.temperature_c' => ['nullable', 'numeric'],
            'readings.*.humidity_pct' => ['nullable', 'numeric'],
            'readings.*.count' => ['nullable', 'integer', 'min:0'],
            'recorded_at' => ['required', 'date'],
        ]);

        $recordedAt = $data['recorded_at'];
        $processed = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($data['readings'] as $index => $reading) {
                $serial = $reading['serial_number'];

                $hardwareItem = HardwareItem::where('serial_number', $serial)
                    ->where('device_id', $device->id)
                    ->where('status', 'active')
                    ->first();

                if (! $hardwareItem) {
                    $errors[] = "Reading {$index}: serial number {$serial} is not registered to this device or is not active.";
                    continue;
                }

                if ($hardwareItem->device_type === 'DHT22') {
                    if (! isset($reading['temperature_c']) || ! isset($reading['humidity_pct'])) {
                        $errors[] = "Reading {$index}: DHT22 reading requires temperature_c and humidity_pct.";
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
                     * Does NOT overwrite a manual entry — only sensor-via
                     * or nonexistent logs are touched. The user can then
                     * override via the web UI's PIN/password flow.
                     */
                    if ($slot->active_hen_count > 0) {
                        $logDate = now()->parse($recordedAt)->toDateString();
                        $existingLog = ProductionLog::where('cage_slot_id', $slot->id)
                            ->where('log_date', $logDate)
                            ->first();

                        if (! $existingLog || $existingLog->logged_via === 'sensor' || $reportedCount !== (int) $existingLog->egg_count) {
                            /*
                             * INVARIANT GUARD — IR break-beam counts are
                             * physically monotonic within a day.  If an
                             * existing sensor-created log already records
                             * a HIGHER egg_count, the sensor likely reset
                             * (e.g. Arduino rebooted).  Do NOT overwrite.
                             */
                            if ($existingLog && $existingLog->logged_via === 'sensor' && $reportedCount < $existingLog->egg_count) {
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
                            ProductionLog::updateOrCreate(
                                ['cage_slot_id' => $slot->id, 'log_date' => $logDate],
                                [
                                    'egg_count' => $reportedCount,
                                    'hen_count' => $henCount,
                                    'hdep' => $henCount > 0 ? round(($reportedCount / $henCount) * 100, 2) : 0,
                                    'logged_via' => 'sensor',
                                    'notes' => 'Sensor reading',
                                ]
                            );
                        }
                    }

                    if ($reportedCount !== $actualOccupancy) {
                        self::createOccupancyMismatchAlert($slot, $reportedCount, $actualOccupancy, $recordedAt);
                    }

                    $processed[] = [
                        'serial_number' => $serial,
                        'type' => 'occupancy',
                        'cage_slot_id' => $slot->id,
                        'mismatch' => $reportedCount !== $actualOccupancy,
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

        $exists = Alert::where('cage_id', $cage?->id)
            ->where('alert_type', 'sensor_reset')
            ->where('is_read', 0)
            ->whereDate('triggered_at', today())
            ->exists();

        if ($exists) {
            return;
        }

        Alert::create([
            'cage_id' => $cage?->id,
            'alert_type' => 'sensor_reset',
            'message' => "IR sensor in {$cageCode} slot {$slot->row_number}-{$slot->column_number} likely reset: count dropped from {$previousCount} to {$reportedCount}",
            'is_read' => 0,
            'triggered_at' => now(),
        ]);
    }

    private static function createOccupancyMismatchAlert($slot, int $reportedCount, int $actualOccupancy, string $recordedAt): void
    {
        $cage = $slot->cage;
        $cageCode = $cage?->cage_code ?? 'Unknown';
        $date = now()->parse($recordedAt)->toDateString();

        $exists = Alert::where('cage_id', $cage?->id)
            ->where('alert_type', 'occupancy_mismatch')
            ->where('is_read', 0)
            ->whereDate('triggered_at', $date)
            ->exists();

        if ($exists) {
            return;
        }

        Alert::create([
            'cage_id' => $cage?->id,
            'alert_type' => 'occupancy_mismatch',
            'message' => "Occupancy mismatch in {$cageCode} slot {$slot->row_number}-{$slot->column_number}: sensor reports {$reportedCount}, records show {$actualOccupancy}",
            'is_read' => 0,
            'triggered_at' => now(),
        ]);
    }
}
