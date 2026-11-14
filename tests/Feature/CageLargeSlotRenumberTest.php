<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saving a cage renumbers its slots through temporary numbers above
 * max + count; with 180 slots those exceed 255 and overflowed TINYINT.
 */
class CageLargeSlotRenumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_cage_with_more_than_127_slots_saves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cage = Cage::create([
            'cage_code' => 'CAGE-BIG', 'location' => 'Test', 'rows' => 3, 'slots_per_row' => 60,
            'max_chickens_per_slot' => 4, 'total_capacity' => 720, 'is_active' => 1,
        ]);
        for ($s = 1; $s <= 180; $s++) {
            CageSlot::create([
                'cage_id' => $cage->id, 'slot_number' => $s, 'row_number' => (int) ceil($s / 60),
                'column_number' => ($s - 1) % 60 + 1, 'current_occupancy' => 0,
            ]);
        }

        $this->actingAs($admin)
            ->put(route('cages.update', $cage), ['rows' => 3, 'slots_per_row' => 60, 'max_chickens_per_slot' => 3])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(540, $cage->fresh()->total_capacity);
        $this->assertSame(range(1, 180), $cage->cageSlots()->orderBy('slot_number')->pluck('slot_number')->all());
    }
}
