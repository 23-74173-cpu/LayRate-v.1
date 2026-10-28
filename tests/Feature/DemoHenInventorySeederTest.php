<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use Database\Seeders\DemoHenInventorySeeder;
use Tests\TestCase;
use RuntimeException;

class DemoHenInventorySeederTest extends TestCase
{
    public function test_demo_hen_inventory_seeder_is_idempotent_and_respects_existing_occupancy(): void
    {
        foreach (['CAGE-A' => ['slots' => 60, 'rows' => 6, 'cols' => 10], 'CAGE-B' => ['slots' => 36, 'rows' => 6, 'cols' => 6], 'CAGE-C' => ['slots' => 36, 'rows' => 6, 'cols' => 6]] as $code => $layout) {
            $count = $layout['slots'];
            $cage = Cage::create([
                'cage_code' => $code, 'rows' => $layout['rows'], 'slots_per_row' => $layout['cols'],
                'max_chickens_per_slot' => 4, 'total_capacity' => $count * 4, 'is_active' => true,
            ]);
            for ($index = 1; $index <= $count; $index++) {
                CageSlot::create([
                    'cage_id' => $cage->id, 'slot_number' => $index,
                    'row_number' => (int) ceil($index / $layout['cols']), 'column_number' => (($index - 1) % $layout['cols']) + 1,
                    'current_occupancy' => 0,
                ]);
            }
        }

        $firstSlot = CageSlot::whereHas('cage', fn ($q) => $q->where('cage_code', 'CAGE-A'))->orderBy('slot_number')->firstOrFail();
        $this->seed(DemoHenInventorySeeder::class);
        $this->seed(DemoHenInventorySeeder::class);

        $this->assertSame(528, Hen::where('tag_code', 'like', 'DEMO-HEN-%')->count());
        $this->assertSame(4, Hen::where('cage_slot_id', $firstSlot->id)->where('is_active', true)->count());
        $this->assertSame(4, (int) $firstSlot->fresh()->current_occupancy);
        $this->assertSame(60, CageSlot::whereHas('cage', fn ($q) => $q->where('cage_code', 'CAGE-A'))->count());
    }

    public function test_demo_hen_inventory_seeder_refuses_unexpected_layout_before_inserting(): void
    {
        Cage::create(['cage_code' => 'CAGE-A', 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => true]);
        Cage::create(['cage_code' => 'CAGE-B', 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => true]);
        Cage::create(['cage_code' => 'CAGE-C', 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => true]);

        try {
            $this->seed(DemoHenInventorySeeder::class);
            $this->fail('Seeder should reject a nonstandard layout.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Expected active CAGE-A', $exception->getMessage());
        }

        $this->assertSame(0, Hen::where('tag_code', 'like', 'DEMO-HEN-%')->count());
    }

    public function test_demo_hen_inventory_seeder_refuses_to_mix_with_existing_live_hens(): void
    {
        foreach (['CAGE-A' => ['n' => 60, 'rows' => 6, 'cols' => 10], 'CAGE-B' => ['n' => 36, 'rows' => 6, 'cols' => 6], 'CAGE-C' => ['n' => 36, 'rows' => 6, 'cols' => 6]] as $code => $layout) {
            $cage = Cage::create(['cage_code' => $code, 'rows' => $layout['rows'], 'slots_per_row' => $layout['cols'], 'max_chickens_per_slot' => 4, 'total_capacity' => $layout['n'] * 4, 'is_active' => true]);
            for ($index = 1; $index <= $layout['n']; $index++) {
                CageSlot::create(['cage_id' => $cage->id, 'slot_number' => $index, 'row_number' => (int) ceil($index / $layout['cols']), 'column_number' => (($index - 1) % $layout['cols']) + 1, 'current_occupancy' => 0]);
            }
        }
        $slot = CageSlot::whereHas('cage', fn ($q) => $q->where('cage_code', 'CAGE-A'))->firstOrFail();
        $real = new Hen(['tag_code' => 'QA-REAL-1', 'breed' => 'ISA Brown', 'flock_age_weeks' => 30, 'is_active' => true]);
        $real->cage_slot_id = $slot->id;
        $real->save();

        try {
            $this->seed(DemoHenInventorySeeder::class);
            $this->fail('Seeder should reject existing live inventory.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('contains live inventory', $exception->getMessage());
        }
        $this->assertSame(0, Hen::where('tag_code', 'like', 'DEMO-HEN-%')->count());
    }
}
