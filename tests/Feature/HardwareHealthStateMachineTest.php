<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use App\Services\DeviceHealthEvaluator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardwareHealthStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function cage(string $code): Cage
    {
        return Cage::create([
            'cage_code' => $code, 'location' => 'Test', 'rows' => 1, 'slots_per_row' => 2,
            'max_chickens_per_slot' => 4, 'total_capacity' => 8, 'is_active' => 1,
        ]);
    }

    private function item(string $type, int $cageId, string $serial = 'S1'): HardwareItem
    {
        return HardwareItem::create([
            'device_type' => $type, 'serial_number' => $serial, 'cage_id' => $cageId,
            'status' => 'active',
        ]);
    }

    private function evaluator(): DeviceHealthEvaluator
    {
        return app(DeviceHealthEvaluator::class);
    }

    public function test_register_dht_reading_goes_online(): void
    {
        $item = $this->item('DHT22', $this->cage('H1')->id);

        $this->evaluator()->registerDhtReading($item, 28.5, 60.0);

        $this->assertEquals('online', $item->fresh()->health_state);
    }

    public function test_online_to_stale_to_disconnected_transition(): void
    {
        $item = $this->item('DHT22', $this->cage('H2')->id);
        $this->evaluator()->registerDhtReading($item, 28.5, 60.0); // online

        $item->update(['last_valid_reading_at' => now()->subMinutes(10)]);
        for ($i = 0; $i < 3; $i++) {
            $this->evaluator()->evaluate($item->fresh());
        }
        $this->assertEquals('stale', $item->fresh()->health_state);
        $this->assertNotNull(Alert::where('alert_type', 'health_stale')->first());

        $item->update(['last_valid_reading_at' => now()->subMinutes(70)]);
        for ($i = 0; $i < 3; $i++) {
            $this->evaluator()->evaluate($item->fresh());
        }
        $this->assertEquals('disconnected', $item->fresh()->health_state);
        $this->assertNotNull(Alert::where('alert_type', 'health_disconnected')->first());
    }

    public function test_faulty_via_implausible_value(): void
    {
        $item = $this->item('DHT22', $this->cage('H3')->id);

        for ($i = 0; $i < 3; $i++) {
            $this->evaluator()->registerDhtReading($item->fresh(), 99.0, 20.0);
        }

        $fresh = $item->fresh();
        $this->assertEquals('faulty', $fresh->health_state);
        $this->assertEquals(DeviceHealthEvaluator::FAULT_IMPLAUSIBLE, $fresh->fault_issue);
    }

    public function test_faulty_via_stuck_value_and_fingerprint_distinct(): void
    {
        $a = DeviceHealthEvaluator::fingerprint(32.2, 78.3);
        $b = DeviceHealthEvaluator::fingerprint(32.3, 78.2);
        $this->assertNotEquals($a, $b, 'genuinely different (temp,hum) must not share a signature');

        $item = $this->item('DHT22', $this->cage('H4')->id);
        for ($i = 0; $i < 11; $i++) {
            $this->evaluator()->registerDhtReading($item->fresh(), 25.0, 60.0);
        }

        $fresh = $item->fresh();
        $this->assertGreaterThanOrEqual(8, (int) $fresh->consecutive_same_readings);
        $this->assertEquals('faulty', $fresh->health_state);
        $this->assertEquals(DeviceHealthEvaluator::FAULT_STUCK, $fresh->fault_issue);
    }

    public function test_recovery_debounce_three_valid_ticks(): void
    {
        $item = $this->item('DHT22', $this->cage('H5')->id);
        $item->update(['health_state' => 'faulty', 'fault_issue' => 'dht_implausible']);

        // tick 1 -> recovering
        $this->evaluator()->registerDhtReading($item->fresh(), 28.0, 60.0);
        $this->assertEquals('recovering', $item->fresh()->health_state);
        $this->assertNotNull(Alert::where('alert_type', 'health_recovering')->first());

        // tick 2 -> still recovering
        $this->evaluator()->registerDhtReading($item->fresh(), 28.1, 60.2);
        $this->assertEquals('recovering', $item->fresh()->health_state);

        // tick 3 -> online
        $this->evaluator()->registerDhtReading($item->fresh(), 28.2, 60.4);
        $this->assertEquals('online', $item->fresh()->health_state);
    }

    public function test_override_row_never_resets_health_clock(): void
    {
        $cage = $this->cage('H6');
        $item = $this->item('DHT22', $cage->id);
        $this->evaluator()->registerDhtReading($item, 28.5, 60.0); // online, last_valid set

        $before = $item->fresh()->last_valid_reading_at;

        // Prompt-5 override row written by updateLog — never goes through
        // register* and must not be treated as a valid live reading.
        EnvironmentalLog::create([
            'cage_id' => $cage->id, 'recorded_at' => now(),
            'temperature_c' => 24.0, 'humidity_pct' => 55.0, 'is_override' => 1,
        ]);

        $this->assertEquals($before->toDateTimeString(), $item->fresh()->last_valid_reading_at->toDateTimeString());

        // Sensor silent, but an override exists "today": health must still escalate.
        $item->update(['last_valid_reading_at' => now()->subMinutes(10)]);
        for ($i = 0; $i < 3; $i++) {
            $this->evaluator()->evaluate($item->fresh());
        }
        $this->assertEquals('stale', $item->fresh()->health_state);
    }

    public function test_relay_advisory_is_observability_only(): void
    {
        $cage = $this->cage('H7');
        $relay = HardwareItem::create([
            'device_type' => 'relay', 'serial_number' => 'R1', 'cage_id' => $cage->id,
            'status' => 'active', 'relay_safety' => false,
        ]);

        $implausible = new EnvironmentalLog(['cage_id' => $cage->id, 'recorded_at' => now(), 'temperature_c' => 99.0, 'humidity_pct' => 10.0]);

        for ($i = 0; $i < 3; $i++) {
            $this->evaluator()->registerRelaySeen($relay->fresh(), $implausible);
        }

        $fresh = $relay->fresh();
        $this->assertEquals('faulty', $fresh->health_state);
        $this->assertEquals(DeviceHealthEvaluator::FAULT_RELAY_ADVISORY, $fresh->fault_issue);
        $this->assertFalse((bool) $fresh->relay_safety, 'advisory must NEVER touch the firmware safety block');
        $this->assertNotNull(Alert::where('alert_type', 'health_safety_advisory')->first());
    }
}