<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class ChickensControllerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
    }

    public function test_inventory_list_search_finds_hens_by_chicken_id_when_tag_code_is_null(): void
    {
        $cage = Cage::create([
            'cage_code' => 'CAGE-SEARCH-TEST',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 1,
            'max_chickens_per_slot' => 10,
            'total_capacity' => 10,
            'is_active' => 1,
        ]);
        $slot = CageSlot::create([
            'cage_id' => $cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 1,
        ]);

        $hen = new Hen([
            'chicken_id' => 'CHK-2026-99999',
            'tag_code' => null,
            'breed' => 'ISA Brown',
            'date_acquired' => now()->subMonths(1)->toDateString(),
            'placement_date' => now()->subMonths(1)->toDateString(),
            'age_at_placement_weeks' => 0,
            'flock_age_weeks' => 4,
            'is_active' => 1,
        ]);
        $hen->cage_slot_id = $slot->id;
        $hen->save();

        $response = $this->actingAs($this->admin)
            ->get(route('chickens.inventory-list', ['search' => '99999']));

        $response->assertOk();
        $response->assertSee('CHK-2026-99999');

        $cageGroups = $response->viewData('cageGroups');
        $visibleHens = $cageGroups->flatten(1)->pluck('chicken_id')->filter();
        $this->assertContains('CHK-2026-99999', $visibleHens->toArray());
    }

    public function test_inventory_list_search_finds_hens_by_tag_code(): void
    {
        $cage = Cage::create([
            'cage_code' => 'CAGE-SEARCH-TAG',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 1,
            'max_chickens_per_slot' => 10,
            'total_capacity' => 10,
            'is_active' => 1,
        ]);
        $slot = CageSlot::create([
            'cage_id' => $cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 1,
        ]);

        $hen = new Hen([
            'chicken_id' => 'CHK-2026-88888',
            'tag_code' => 'LEG-BAND-42',
            'breed' => 'ISA Brown',
            'date_acquired' => now()->subMonths(1)->toDateString(),
            'placement_date' => now()->subMonths(1)->toDateString(),
            'age_at_placement_weeks' => 0,
            'flock_age_weeks' => 4,
            'is_active' => 1,
        ]);
        $hen->cage_slot_id = $slot->id;
        $hen->save();

        $response = $this->actingAs($this->admin)
            ->get(route('chickens.inventory-list', ['search' => 'LEG-BAND']));

        $response->assertOk();
        $response->assertSee('LEG-BAND-42');
    }
}
