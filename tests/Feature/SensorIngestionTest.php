<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Device;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use App\Models\Hen;
use App\Models\SensorOccupancyReading;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
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
            'recorded_at' => now()->toDateTimeString(),
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
}
