<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\HardwareItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardwareCageAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dht22_spare_can_be_assigned_to_cage_via_edit()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $cage = Cage::create([
            'cage_code' => 'CAGE-Z',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        CageSlot::create([
            'cage_id' => $cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 0,
        ]);

        $hardware = HardwareItem::create([
            'device_type' => 'DHT22',
            'serial_number' => 'DHT22_TEST_001',
            'status' => 'spare',
        ]);

        $response = $this->actingAs($user)->put("/cages/{$cage->id}", [
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'is_active' => 1,
            'dht22_count' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $hardware->refresh();
        $this->assertEquals($cage->id, $hardware->cage_id);
        $this->assertNull($hardware->cage_slot_id);
        $this->assertEquals('active', $hardware->status);
    }

    /** @test */
    public function dht22_spare_can_be_assigned_to_cage_via_browser_form_post()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $cage = Cage::create([
            'cage_code' => 'CAGE-Y',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        CageSlot::create([
            'cage_id' => $cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 0,
        ]);

        $hardware = HardwareItem::create([
            'device_type' => 'DHT22',
            'serial_number' => 'DHT22_TEST_002',
            'status' => 'spare',
        ]);

        $response = $this->actingAs($user)->post("/cages/{$cage->id}", [
            '_method' => 'PUT',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'is_active' => 1,
            'dht22_count' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $hardware->refresh();
        $this->assertEquals($cage->id, $hardware->cage_id);
        $this->assertNull($hardware->cage_slot_id);
        $this->assertEquals('active', $hardware->status);
    }

    /** @test */
    public function dht22_active_unassigned_can_be_assigned_to_cage_via_edit()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $cage = Cage::create([
            'cage_code' => 'CAGE-X',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        CageSlot::create([
            'cage_id' => $cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 0,
        ]);

        $hardware = HardwareItem::create([
            'device_type' => 'DHT22',
            'serial_number' => 'DHT22_TEST_003',
            'status' => 'active',
            'cage_id' => null,
            'cage_slot_id' => null,
        ]);

        $response = $this->actingAs($user)->put("/cages/{$cage->id}", [
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'is_active' => 1,
            'dht22_count' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $hardware->refresh();
        $this->assertEquals($cage->id, $hardware->cage_id);
        $this->assertNull($hardware->cage_slot_id);
        $this->assertEquals('active', $hardware->status);
    }

    /** @test */
    public function ir_breakbeam_active_unassigned_can_be_assigned_to_slot_via_edit()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $cage = Cage::create([
            'cage_code' => 'CAGE-W',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $slot = CageSlot::create([
            'cage_id' => $cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 0,
        ]);

        $hardware = HardwareItem::create([
            'device_type' => 'IR_breakbeam',
            'serial_number' => 'IR_TEST_001',
            'status' => 'active',
            'cage_id' => null,
            'cage_slot_id' => null,
        ]);

        $response = $this->actingAs($user)->put("/cages/{$cage->id}", [
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'is_active' => 1,
            'slots' => [
                $slot->id => ['has_sensor' => 1],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $hardware->refresh();
        $this->assertNull($hardware->cage_id);
        $this->assertEquals($slot->id, $hardware->cage_slot_id);
        $this->assertEquals('active', $hardware->status);
    }
}


