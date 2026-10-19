<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Device;
use App\Models\EnvironmentalLog;
use App\Models\EggCountEvent;
use App\Models\HardwareItem;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\SensorOccupancyReading;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SensorIngestionTest extends TestCase
{
    private User $admin;
    private Device $device;
    private Cage $cage;
    private CageSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@layrate.local')->first();

        // Dedicated test cage/slot so seeded data does not pollute counts.
        $this->cage = Cage::create([
            'cage_code' => 'CAGE-INGEST',
            'location' => 'Test ingestion',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $this->slot = CageSlot::create([
            'cage_id' => $this->cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 0,
        ]);

        $this->device = Device::create([
            'name' => 'Test Pi',
            'api_key_hash' => Hash::make('lr_testkey_placeholder'),
            'is_active' => true,
        ]);
    }

    private function deviceKey(): string
    {
        $plain = 'lr_testkey_known';
        $this->device->update(['api_key_hash' => Hash::make($plain)]);

        return $plain;
    }

    private function dht22Item(): HardwareItem
    {
        return HardwareItem::create([
            'device_type' => 'DHT22',
            'serial_number' => 'DHT22-TEST-001',
            'cage_id' => $this->cage->id,
            'device_id' => $this->device->id,
            'status' => 'active',
        ]);
    }

    private function breakbeamItem(): HardwareItem
    {
        return HardwareItem::create([
            'device_type' => 'IR_breakbeam',
            'serial_number' => 'IRBBS-TEST-001',
            'cage_slot_id' => $this->slot->id,
            'device_id' => $this->device->id,
            'status' => 'active',
        ]);
    }

    private function createHens(int $count): void
    {
        $this->slot->update(['current_occupancy' => $count]);

        for ($i = 1; $i <= $count; $i++) {
            $hen = Hen::create([
                'tag_code' => "INGEST-HEN{$i}",
                'breed' => 'ISA Brown',
                'flock_age_weeks' => 28,
                'date_acquired' => now()->subMonths(6)->toDateString(),
                'placement_date' => now()->subMonths(6)->toDateString(),
                'age_at_placement_weeks' => 0,
                'is_active' => 1,
            ]);
            $hen->cage_slot_id = $this->slot->id;
            $hen->save();
        }
    }

    private function postReadings(array $payload, ?string $key = null)
    {
        $headers = [];
        if ($key !== null) {
            $headers['X-Device-Key'] = $key;
        }

        return $this->postJson('/api/sensor-readings', $payload, $headers);
    }

    private function assertNoTestEnvironmentalLogs(): void
    {
        $this->assertEquals(0, EnvironmentalLog::where('cage_id', $this->cage->id)->count());
    }

    private function assertNoTestOccupancyReadings(): void
    {
        $this->assertEquals(0, SensorOccupancyReading::where('cage_slot_id', $this->slot->id)->count());
    }

    public function test_valid_dht22_payload_creates_environmental_log(): void
    {
        $this->dht22Item();
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'DHT22-TEST-001',
                    'temperature_c' => 28.5,
                    'humidity_pct' => 65.0,
                ],
            ],
            'recorded_at' => now()->addSecond()->toDateTimeString(),
        ], $key);

        $response->assertOk();
        $response->assertJsonPath('accepted', 1);

        $this->assertDatabaseHas('environmental_logs', [
            'cage_id' => $this->cage->id,
            'temperature_c' => 28.5,
            'humidity_pct' => 65.0,
        ]);
    }

    public function test_invalid_device_key_returns_401_and_writes_nothing(): void
    {
        $this->dht22Item();

        $this->assertNoTestEnvironmentalLogs();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'DHT22-TEST-001',
                    'temperature_c' => 28.5,
                    'humidity_pct' => 65.0,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], 'wrong-key');

        $response->assertUnauthorized();
        $this->assertNoTestEnvironmentalLogs();
    }

    public function test_missing_device_key_returns_401(): void
    {
        $this->dht22Item();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'DHT22-TEST-001',
                    'temperature_c' => 28.5,
                    'humidity_pct' => 65.0,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_temperature_threshold_crossing_creates_alert(): void
    {
        Setting::set('temp_max', 30);
        Setting::set('temp_min', 18);
        Setting::set('hum_max', 70);
        Setting::set('hum_min', 40);

        $this->dht22Item();
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'DHT22-TEST-001',
                    'temperature_c' => 31.5,
                    'humidity_pct' => 55.0,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertOk();

        $this->assertDatabaseHas('alerts', [
            'cage_id' => $this->cage->id,
            'alert_type' => 'temperature_high',
            'is_read' => 0,
        ]);
    }

    public function test_breakbeam_count_matching_occupancy_creates_reading_but_no_alert(): void
    {
        $this->createHens(4);
        $item = $this->breakbeamItem();
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'IRBBS-TEST-001',
                    'count' => 4,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertOk();

        $this->assertDatabaseHas('sensor_occupancy_readings', [
            'hardware_item_id' => $item->id,
            'cage_slot_id' => $this->slot->id,
            'reported_count' => 4,
        ]);

        $this->assertDatabaseMissing('alerts', [
            'cage_id' => $this->cage->id,
            'alert_type' => 'occupancy_mismatch',
        ]);

        // Occupancy records must be untouched.
        $this->assertEquals(4, $this->slot->fresh()->current_occupancy);
        $this->assertEquals(4, $this->slot->hens()->where('is_active', 1)->count());
    }

    public function test_breakbeam_count_disagreeing_with_occupancy_creates_mismatch_alert_and_leaves_placement_intact(): void
    {
        $this->createHens(4);
        $item = $this->breakbeamItem();
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'IRBBS-TEST-001',
                    'count' => 3,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertOk();

        $this->assertDatabaseHas('sensor_occupancy_readings', [
            'hardware_item_id' => $item->id,
            'cage_slot_id' => $this->slot->id,
            'reported_count' => 3,
        ]);

        $this->assertDatabaseHas('alerts', [
            'cage_id' => $this->cage->id,
            'alert_type' => 'occupancy_mismatch',
            'is_read' => 0,
        ]);

        // Critical P0-P4 protection: placement/occupancy must not change.
        $this->assertEquals(4, $this->slot->fresh()->current_occupancy);
        $this->assertEquals(4, $this->slot->hens()->where('is_active', 1)->count());
    }

    public function test_breakbeam_count_exceeding_hen_count_is_rejected(): void
    {
        $this->createHens(1);
        $this->breakbeamItem();
        HardwareItem::create([
            'device_type' => 'relay',
            'serial_number' => 'RELAY-TEST-001',
            'cage_id' => $this->cage->id,
            'device_id' => $this->device->id,
            'status' => 'active',
        ]);
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'RELAY-TEST-001',
                    'relay_status' => 'off',
                ],
                [
                    'serial_number' => 'IRBBS-TEST-001',
                    'count' => 2,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        // Partial success: relay accepted, over-count egg log rejected.
        $response->assertStatus(207);
        $response->assertJsonPath('accepted', 1);
        $response->assertJsonPath('errors.0', fn ($msg) => str_contains($msg, 'exceeds hen count'));

        // No egg log may be created from an impossible sensor count.
        $this->assertEquals(0, ProductionLog::where('cage_slot_id', $this->slot->id)->count());

        // Raw occupancy datum is still preserved for diagnostics.
        $this->assertDatabaseHas('sensor_occupancy_readings', [
            'cage_slot_id' => $this->slot->id,
            'reported_count' => 2,
        ]);

        // Farmer is alerted to check the sensor.
        $this->assertDatabaseHas('alerts', [
            'cage_id' => $this->cage->id,
            'alert_type' => 'egg_over_hen',
            'is_read' => 0,
        ]);
    }

    public function test_breakbeam_count_equal_to_hen_count_is_accepted(): void
    {
        $this->createHens(2);
        $this->breakbeamItem();
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'IRBBS-TEST-001',
                    'count' => 2,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertOk();

        $this->assertDatabaseHas('production_logs', [
            'cage_slot_id' => $this->slot->id,
            'egg_count' => 2,
            'hen_count' => 2,
        ]);

        $this->assertDatabaseHas('egg_count_events', [
            'cage_slot_id' => $this->slot->id,
            'hardware_item_id' => HardwareItem::where('serial_number', 'IRBBS-TEST-001')->value('id'),
            'event_type' => 'sensor_trigger',
            'new_value' => 2,
            'source' => 'IRBBS-TEST-001',
        ]);
    }

    public function test_rapid_monotonic_sensor_counts_each_append_one_event(): void
    {
        $this->createHens(4);
        $this->breakbeamItem();
        $key = $this->deviceKey();
        $base = now()->startOfSecond();
        $index = 0;

        foreach ([1, 2, 3] as $count) {
            $this->postReadings([
                'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => $count]],
                'recorded_at' => $base->copy()->addSeconds(++$index)->toDateTimeString(),
            ], $key)->assertOk();
        }

        $this->assertSame(3, EggCountEvent::where('cage_slot_id', $this->slot->id)
            ->where('event_type', 'sensor_trigger')->count());
        $this->assertSame(3, (int) ProductionLog::where('cage_slot_id', $this->slot->id)->value('egg_count'));
    }

    public function test_sensor_count_update_increments_live_count_stream_version(): void
    {
        $this->createHens(4);
        $this->breakbeamItem();
        $key = $this->deviceKey();
        Cache::forever('egg_count_sse_version', 17);

        $this->postReadings([
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => 1]],
            'recorded_at' => now()->toDateTimeString(),
        ], $key)->assertOk();

        $this->assertSame(18, (int) Cache::get('egg_count_sse_version'));
    }

    public function test_sensor_count_update_invalidates_live_recent_logs_stream(): void
    {
        $this->createHens(4);
        $this->breakbeamItem();
        Cache::forever('egg_logs_sse_version', 8);

        $this->postReadings([
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => 1]],
            'recorded_at' => now()->toDateTimeString(),
        ], $this->deviceKey())->assertOk();

        $this->assertSame(9, (int) Cache::get('egg_logs_sse_version'));
    }

    public function test_duplicate_sensor_payload_is_idempotent_but_distinct_trigger_is_not_dropped_by_time_window(): void
    {
        $this->createHens(4);
        $this->breakbeamItem();
        $key = $this->deviceKey();
        $stamp = now()->toDateTimeString();
        $payload = [
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => 1]],
            'recorded_at' => $stamp,
        ];

        $this->postReadings($payload, $key)->assertOk();
        $this->postReadings($payload, $key)->assertJsonPath('processed.0.duplicate', true);
        $this->postReadings([
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => 2]],
            'recorded_at' => now()->addSecond()->toDateTimeString(),
        ], $key)->assertOk();

        $this->assertSame(2, EggCountEvent::where('cage_slot_id', $this->slot->id)
            ->where('event_type', 'sensor_trigger')->count());
    }

    public function test_sensor_trigger_count_records_rapid_events_when_cumulative_counter_changes_later(): void
    {
        $this->createHens(4);
        $this->breakbeamItem();
        $key = $this->deviceKey();
        $base = now();

        $this->postReadings([
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => 1, 'trigger_count' => 1, 'new_trigger_count' => 1]],
            'recorded_at' => $base->toDateTimeString(),
        ], $key)->assertOk();

        $this->postReadings([
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => 1, 'trigger_count' => 2, 'new_trigger_count' => 1]],
            'recorded_at' => $base->copy()->addSecond()->toDateTimeString(),
        ], $key)->assertOk();

        $this->assertSame(2, EggCountEvent::where('cage_slot_id', $this->slot->id)
            ->where('event_type', 'sensor_trigger')->count());
        $this->assertSame(1, (int) ProductionLog::where('cage_slot_id', $this->slot->id)->value('egg_count'));
        $this->postReadings([
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => 1, 'trigger_count' => 2, 'new_trigger_count' => 0]],
            'recorded_at' => $base->copy()->addSeconds(2)->toDateTimeString(),
        ], $key)->assertOk();
        $this->assertSame(2, EggCountEvent::where('cage_slot_id', $this->slot->id)
            ->where('event_type', 'sensor_trigger')->count());

        $this->postReadings([
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => 1, 'trigger_count' => 3, 'new_trigger_count' => 1]],
            'recorded_at' => $base->copy()->addSeconds(3)->toDateTimeString(),
        ], $key)->assertOk();
        $this->assertSame(3, EggCountEvent::where('cage_slot_id', $this->slot->id)
            ->where('event_type', 'sensor_trigger')->count());
    }

    public function test_bridge_queue_replay_retry_with_same_sensor_timestamp_does_not_duplicate_event(): void
    {
        $this->createHens(4);
        $this->breakbeamItem();
        $key = $this->deviceKey();
        $stamp = now()->toDateTimeString();
        $payload = [
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'count' => 1,
                'trigger_count' => 1, 'new_trigger_count' => 1]],
            'recorded_at' => $stamp,
        ];

        $this->postReadings($payload, $key)->assertOk();
        $this->postReadings($payload, $key)->assertJsonPath('processed.0.duplicate', true);

        $item = HardwareItem::where('serial_number', 'IRBBS-TEST-001')->firstOrFail();
        $this->assertSame(1, EggCountEvent::where('hardware_item_id', $item->id)
            ->where('event_type', 'sensor_trigger')->count());
        $this->assertSame(1, ProductionLog::where('cage_slot_id', $this->slot->id)->value('egg_count'));
    }

    public function test_health_probe_acknowledges_app_without_touching_sensor_or_egg_state(): void
    {
        $this->createHens(4);
        $item = $this->breakbeamItem();
        $item->update(['last_valid_reading_at' => now()->subMinutes(4), 'health_state' => 'stale']);
        $key = $this->deviceKey();
        $healthBefore = $item->fresh()->last_valid_reading_at;

        $response = $this->postJson('/api/sensor-readings', [
            'health_probe' => true,
            'probe_id' => 'probe-test-1',
            'recorded_at' => now()->toIso8601String(),
            'readings' => [[
                'serial_number' => 'IRBBS-TEST-001',
                'health_probe' => true,
            ]],
        ], [
            'X-Device-Key' => $key,
            'X-LayRate-Health-Probe' => '1',
        ]);

        $response->assertOk()->assertJsonPath('processed.0.type', 'health_probe');
        $this->assertSame($healthBefore->toDateTimeString(), $item->fresh()->last_valid_reading_at->toDateTimeString());
        $this->assertSame(0, ProductionLog::where('cage_slot_id', $this->slot->id)->count());
        $this->assertSame(0, SensorOccupancyReading::where('hardware_item_id', $item->id)->count());
        $this->assertSame(0, EggCountEvent::where('hardware_item_id', $item->id)->count());
    }

    public function test_health_probe_requires_explicit_marker_header(): void
    {
        $this->breakbeamItem();

        $this->postJson('/api/sensor-readings', [
            'health_probe' => true,
            'probe_id' => 'probe-test-no-header',
            'recorded_at' => now()->toIso8601String(),
            'readings' => [['serial_number' => 'IRBBS-TEST-001', 'health_probe' => true]],
        ], ['X-Device-Key' => $this->deviceKey()])
            ->assertStatus(422)
            ->assertJsonPath('accepted', 0);
    }

    public function test_editing_sensor_log_marks_manual_so_sensor_cannot_revert_it(): void
    {
        $this->createHens(2);
        $this->breakbeamItem();
        $key = $this->deviceKey();
        $today = \App\Services\ReportingDateService::reportingDateString();

        // Sensor logs 2 (valid for 2 hens).
        $this->postReadings([
            'readings' => [
                ['serial_number' => 'IRBBS-TEST-001', 'count' => 2],
            ],
            'recorded_at' => now()->subMinutes(2)->toDateTimeString(),
        ], $key)->assertOk();

        $log = ProductionLog::where('cage_slot_id', $this->slot->id)->firstOrFail();
        $this->assertEquals('sensor', $log->logged_via);

        // User corrects 2 -> 1 via the edit modal (Recent logs pencil).
        $this->actingAs($this->admin)
            ->put(route('eggs.logging.update', $log), [
                'log_date' => $today,
                'egg_count' => 1,
            ])
            ->assertRedirect(route('eggs.logging'));

        $this->assertDatabaseHas('production_logs', [
            'id' => $log->id,
            'egg_count' => 1,
            'logged_via' => 'manual',
        ]);
        $this->assertSame(1, EggCountEvent::where('production_log_id', $log->id)
            ->where('event_type', 'manual_override')
            ->where('old_value', 2)
            ->where('new_value', 1)
            ->count());

        // Sensor re-posts 2 (e.g. next temperature-triggered bridge POST
        // carries the same monotonic count). It is acknowledged, but must NOT
        // revert the correction or create a sensor trigger event.
        $response = $this->postReadings([
            'readings' => [
                ['serial_number' => 'IRBBS-TEST-001', 'count' => 2],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertOk();
        $response->assertJsonPath('accepted', 1);

        $this->assertDatabaseHas('production_logs', [
            'id' => $log->id,
            'egg_count' => 1,
            'logged_via' => 'manual',
        ]);
        $this->assertSame(1, EggCountEvent::where('production_log_id', $log->id)
            ->where('event_type', 'manual_override')->count());
        $this->assertSame(0, EggCountEvent::where('production_log_id', $log->id)
            ->where('event_type', 'sensor_trigger')->count());
    }

    public function test_serial_number_not_linked_to_device_is_rejected(): void
    {
        $otherDevice = Device::create([
            'name' => 'Other Pi',
            'api_key_hash' => Hash::make('other-key'),
            'is_active' => true,
        ]);

        HardwareItem::create([
            'device_type' => 'DHT22',
            'serial_number' => 'DHT22-OTHER-001',
            'cage_id' => $this->cage->id,
            'device_id' => $otherDevice->id,
            'status' => 'active',
        ]);

        $key = $this->deviceKey();

        $this->assertNoTestEnvironmentalLogs();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'DHT22-OTHER-001',
                    'temperature_c' => 28.5,
                    'humidity_pct' => 65.0,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertStatus(422);
        $response->assertJsonPath('accepted', 0);
        $response->assertJsonPath('errors.0', fn ($msg) => str_contains($msg, 'not registered to this device'));

        $this->assertNoTestEnvironmentalLogs();
    }

    public function test_partial_batch_valid_persist_invalid_reported(): void
    {
        $item = $this->dht22Item();
        $key = $this->deviceKey();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'DHT22-TEST-001',
                    'temperature_c' => 26.0,
                    'humidity_pct' => 60.0,
                ],
                [
                    'serial_number' => 'DHT22-MISSING-001',
                    'temperature_c' => 99.0,
                    'humidity_pct' => 99.0,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertStatus(207);
        $response->assertJsonPath('accepted', 1);
        $response->assertJsonPath('processed.0.serial_number', 'DHT22-TEST-001');
        $response->assertJsonPath('errors.0', fn ($msg) => str_contains($msg, 'DHT22-MISSING-001'));

        // Valid reading persisted; invalid one did not.
        $this->assertDatabaseHas('environmental_logs', [
            'cage_id' => $this->cage->id,
            'temperature_c' => 26.0,
            'humidity_pct' => 60.0,
        ]);

        $this->assertDatabaseMissing('environmental_logs', [
            'temperature_c' => 99.0,
            'humidity_pct' => 99.0,
        ]);
    }

    public function test_duplicate_submission_is_idempotent(): void
    {
        $item = $this->dht22Item();
        $key = $this->deviceKey();

        $payload = [
            'readings' => [
                [
                    'serial_number' => 'DHT22-TEST-001',
                    'temperature_c' => 31.0,
                    'humidity_pct' => 62.0,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ];

        $this->postReadings($payload, $key)->assertOk();
        $this->postReadings($payload, $key)->assertOk();

        // Only one EnvironmentalLog row despite two identical submissions.
        $this->assertEquals(
            1,
            EnvironmentalLog::where('cage_id', $this->cage->id)
                ->where('temperature_c', 31.0)
                ->where('humidity_pct', 62.0)
                ->count()
        );

        // Only one threshold alert should have been created.
        $this->assertEquals(
            1,
            Alert::where('cage_id', $this->cage->id)
                ->where('alert_type', 'temperature_high')
                ->count()
        );
    }

    public function test_malformed_payload_returns_validation_error_without_writes(): void
    {
        $this->dht22Item();
        $key = $this->deviceKey();

        $this->assertNoTestEnvironmentalLogs();
        $this->assertNoTestOccupancyReadings();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'DHT22-TEST-001',
                    'temperature_c' => 'not-a-number',
                    'humidity_pct' => 65.0,
                ],
            ],
            'recorded_at' => 'not-a-date',
        ], $key);

        $response->assertUnprocessable();
        $this->assertNoTestEnvironmentalLogs();
        $this->assertNoTestOccupancyReadings();
    }

    public function test_admin_can_create_device_and_see_key_once(): void
    {
        $response = $this->actingAs($this->admin)->post('/devices', [
            'name' => 'New Pi',
        ]);

        $response->assertRedirect('/hardware');
        $response->assertSessionHas('new_device_key');
        $response->assertSessionHas('new_device_id');

        $this->assertDatabaseHas('devices', [
            'name' => 'New Pi',
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_regenerate_device_key(): void
    {
        $response = $this->actingAs($this->admin)->post(route('devices.regenerate-key', $this->device), []);

        $response->assertRedirect('/hardware');
        $response->assertSessionHas('new_device_key');
    }

    public function test_inactive_device_key_is_rejected(): void
    {
        $this->device->update(['is_active' => false]);
        $this->dht22Item();
        $key = $this->deviceKey();

        $this->assertNoTestEnvironmentalLogs();

        $response = $this->postReadings([
            'readings' => [
                [
                    'serial_number' => 'DHT22-TEST-001',
                    'temperature_c' => 28.5,
                    'humidity_pct' => 65.0,
                ],
            ],
            'recorded_at' => now()->toDateTimeString(),
        ], $key);

        $response->assertUnauthorized();
        $this->assertNoTestEnvironmentalLogs();
    }

    private function dhtPayload(float $temp, string $recordedAt): array
    {
        return [
            'readings' => [
                [
                    'serial_number' => 'DHT22-TEST-001',
                    'temperature_c' => $temp,
                    'humidity_pct' => 60.0,
                ],
            ],
            'recorded_at' => $recordedAt,
        ];
    }

    public function test_dht22_readings_inside_save_interval_are_not_stored(): void
    {
        config(['environment.dht22_save_interval_seconds' => 30]);
        $this->dht22Item();
        $key = $this->deviceKey();
        $start = now()->startOfMinute();

        $this->postReadings($this->dhtPayload(27.0, $start->toDateTimeString()), $key)->assertOk();
        $this->postReadings($this->dhtPayload(27.1, $start->copy()->addSeconds(2)->toDateTimeString()), $key)
            ->assertOk()
            ->assertJsonPath('accepted', 1);
        $this->postReadings($this->dhtPayload(27.2, $start->copy()->addSeconds(30)->toDateTimeString()), $key)->assertOk();

        $this->assertEquals(
            [27.0, 27.2],
            EnvironmentalLog::where('cage_id', $this->cage->id)->orderBy('recorded_at')
                ->pluck('temperature_c')->map(fn ($t) => (float) $t)->all()
        );
    }

    public function test_dht22_reading_skipped_by_save_interval_still_raises_alert(): void
    {
        config(['environment.dht22_save_interval_seconds' => 30]);
        Setting::set('temp_max', 32);
        $this->dht22Item();
        $key = $this->deviceKey();
        $start = now()->startOfMinute();

        $this->postReadings($this->dhtPayload(27.0, $start->toDateTimeString()), $key)->assertOk();
        $this->postReadings($this->dhtPayload(36.0, $start->copy()->addSeconds(2)->toDateTimeString()), $key)->assertOk();

        $this->assertEquals(1, EnvironmentalLog::where('cage_id', $this->cage->id)->count());
        $this->assertEquals(1, Alert::where('cage_id', $this->cage->id)->where('alert_type', 'temperature_high')->count());
    }

    public function test_dht22_save_interval_zero_stores_every_reading(): void
    {
        config(['environment.dht22_save_interval_seconds' => 0]);
        $this->dht22Item();
        $key = $this->deviceKey();
        $start = now()->startOfMinute();

        $this->postReadings($this->dhtPayload(27.0, $start->toDateTimeString()), $key)->assertOk();
        $this->postReadings($this->dhtPayload(27.1, $start->copy()->addSeconds(2)->toDateTimeString()), $key)->assertOk();

        $this->assertEquals(2, EnvironmentalLog::where('cage_id', $this->cage->id)->count());
    }

    public function test_device_key_lookup_is_remembered_after_first_request(): void
    {
        $this->dht22Item();
        $key = $this->deviceKey();
        $this->assertNull($this->device->fresh()->api_key_lookup);

        $this->postReadings($this->dhtPayload(27.0, now()->toDateTimeString()), $key)->assertOk();

        $this->assertEquals(Device::lookupFor($key), $this->device->fresh()->api_key_lookup);

        // Second request goes through the fast lookup and still works.
        $this->postReadings($this->dhtPayload(27.5, now()->addMinute()->toDateTimeString()), $key)->assertOk();
    }

    public function test_replaced_device_key_stops_working_through_lookup(): void
    {
        $this->dht22Item();
        $oldKey = $this->deviceKey();
        $this->postReadings($this->dhtPayload(27.0, now()->toDateTimeString()), $oldKey)->assertOk();
        $this->assertNotNull($this->device->fresh()->api_key_lookup);

        $this->device->fresh()->update(['api_key_hash' => Hash::make('lr_testkey_replacement')]);

        $this->postReadings($this->dhtPayload(27.5, now()->addMinute()->toDateTimeString()), $oldKey)->assertUnauthorized();
        $this->postReadings($this->dhtPayload(27.5, now()->addMinutes(2)->toDateTimeString()), 'lr_testkey_replacement')->assertOk();
    }

    public function test_regenerated_device_key_works_and_old_key_does_not(): void
    {
        $this->dht22Item();
        $oldKey = $this->deviceKey();
        $this->postReadings($this->dhtPayload(27.0, now()->toDateTimeString()), $oldKey)->assertOk();

        $newKey = $this->device->fresh()->generateApiKey();

        $this->postReadings($this->dhtPayload(27.5, now()->addMinute()->toDateTimeString()), $oldKey)->assertUnauthorized();
        $this->postReadings($this->dhtPayload(27.5, now()->addMinutes(2)->toDateTimeString()), $newKey)->assertOk();
    }

    public function test_inactive_device_key_is_rejected_after_lookup_was_remembered(): void
    {
        $this->dht22Item();
        $key = $this->deviceKey();
        $this->postReadings($this->dhtPayload(27.0, now()->toDateTimeString()), $key)->assertOk();

        $this->device->fresh()->update(['is_active' => false]);

        $this->postReadings($this->dhtPayload(27.5, now()->addMinute()->toDateTimeString()), $key)->assertUnauthorized();
    }
}
