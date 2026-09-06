<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CagePositionBoundsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unplaced_cage_can_be_placed_within_stored_farm_layout_bounds()
    {
        Setting::set('farm_grid_rows', 12);
        Setting::set('farm_grid_cols', 24);
        $user = User::factory()->create(['role' => 'admin']);

        $cage = Cage::create([
            'cage_code' => 'CAGE-A',
            'location' => 'Test',
            'rows' => 5,
            'slots_per_row' => 12,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 240,
            'is_active' => 1,
        ]);

        $response = $this->actingAs($user)->patchJson(route('cages.position', $cage), [
            'location_row' => 1,
            'location_column' => 1,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $cage->refresh();
        $this->assertEquals(1, $cage->location_row);
        $this->assertEquals(1, $cage->location_column);
    }

    /** @test */
    public function placement_outside_stored_farm_layout_bounds_is_rejected()
    {
        Setting::set('farm_grid_rows', 12);
        Setting::set('farm_grid_cols', 24);
        $user = User::factory()->create(['role' => 'admin']);

        $cage = Cage::create([
            'cage_code' => 'CAGE-A',
            'location' => 'Test',
            'rows' => 5,
            'slots_per_row' => 12,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 240,
            'is_active' => 1,
        ]);

        // row 8 + 5 = 13 surpasses the 12-row layout.
        $response = $this->actingAs($user)->patchJson(route('cages.position', $cage), [
            'location_row' => 8,
            'location_column' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'Position out of bounds']);

        // column 14 + 12 = 26 surpasses the 24-column layout.
        $response = $this->actingAs($user)->patchJson(route('cages.position', $cage), [
            'location_row' => 1,
            'location_column' => 14,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'Position out of bounds']);
    }

    /** @test */
    public function batch_layout_save_honors_stored_farm_layout_bounds()
    {
        Setting::set('farm_grid_rows', 12);
        Setting::set('farm_grid_cols', 24);
        $user = User::factory()->create(['role' => 'admin']);

        $cage = Cage::create([
            'cage_code' => 'CAGE-A',
            'location' => 'Test',
            'rows' => 5,
            'slots_per_row' => 12,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 240,
            'is_active' => 1,
        ]);

        $good = $this->actingAs($user)->postJson(route('cages.batch-position'), [
            'positions' => [
                ['id' => $cage->id, 'location_row' => 1, 'location_column' => 1],
            ],
        ]);
        $good->assertOk();
        $good->assertJson(['success' => true]);

        // column 14 + 12 = 26 surpasses the 24-column layout.
        $unplaced = Cage::create([
            'cage_code' => 'CAGE-B',
            'location' => 'Test',
            'rows' => 5,
            'slots_per_row' => 12,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 240,
            'is_active' => 1,
        ]);

        $bad = $this->actingAs($user)->postJson(route('cages.batch-position'), [
            'positions' => [
                ['id' => $unplaced->id, 'location_row' => 1, 'location_column' => 14],
            ],
        ]);
        $bad->assertStatus(422);
        $bad->assertJson(['success' => false, 'message' => 'Position out of bounds']);

        $unplaced->refresh();
        $this->assertNull($unplaced->location_row);
        $this->assertNull($unplaced->location_column);
    }
}