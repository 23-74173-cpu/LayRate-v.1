<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * P3: Delete-flow invariants — cage deletion with various preserve_* options.
 *
 * Guards against schema-invasive changes: FK nulling, hen rehoming,
 * hard-delete behavior. Items B, Item 2 (P2).
 */
class CageDeleteFlowTest extends TestCase
{
    private User $user;
    private Cage $cage;
    private int $activeHenCount;
    private FeedBatch $feedBatch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'admin@layrate.local')->first();
        $this->feedBatch = FeedBatch::first();

        // Dedicated test cage so data isolation is clear
        $this->cage = Cage::create([
            'cage_code'             => 'DEL-TEST',
            'location'              => 'test',
            'rows'                  => 2,
            'slots_per_row'         => 3,
            'max_chickens_per_slot' => 4,
            'total_capacity'        => 24,
            'is_active'             => 1,
        ]);

        // 6 slots, 2 active hens each = 12 active hens
        $this->activeHenCount = 0;
        for ($row = 1; $row <= 2; $row++) {
            for ($col = 1; $col <= 3; $col++) {
                $slotNum = ($row - 1) * 3 + $col;
                $slot = CageSlot::create([
                    'cage_id'           => $this->cage->id,
                    'slot_number'       => $slotNum,
                    'row_number'        => $row,
                    'column_number'     => $col,
                    'current_occupancy' => 2,
                ]);

                for ($h = 0; $h < 2; $h++) {
                    $s = substr(uniqid(), -8);
                    $hen = new Hen;
                    $hen->chicken_id = "DT-{$s}";
                    $hen->breed = 'ISA Brown';
                    $hen->tag_code = "DT-{$s}";
                    $hen->date_acquired = now()->subDays(30);
                    $hen->flock_age_weeks = 20;
                    $hen->cage_slot_id = $slot->id;
                    $hen->is_active = 1;
                    $hen->placement_date = now()->subDays(30);
                    $hen->age_at_placement_weeks = 0;
                    $hen->save();
                    $this->activeHenCount++;
                }
            }
        }

        // 1 inactive hen (should retain cage_slot_id after deletion — P2 guard)
        $inactiveSlot = $this->cage->cageSlots->first();
        $s = substr(uniqid(), -8);
        $inactiveHen = new Hen;
        $inactiveHen->chicken_id = "DI-{$s}";
        $inactiveHen->breed = 'ISA Brown';
        $inactiveHen->tag_code = "DI-{$s}";
        $inactiveHen->date_acquired = now()->subDays(60);
        $inactiveHen->flock_age_weeks = 30;
        $inactiveHen->cage_slot_id = $inactiveSlot->id;
        $inactiveHen->is_active = 0;
        $inactiveHen->placement_date = now()->subDays(60);
        $inactiveHen->age_at_placement_weeks = 0;
        $inactiveHen->save();

        // Logs for delete-flow assertions
        $slotIds = $this->cage->cageSlots->pluck('id');
        foreach ($slotIds as $sid) {
            $pl = new ProductionLog;
            $pl->cage_slot_id = $sid;
            $pl->log_date = now()->subDay()->toDateString();
            $pl->egg_count = 4;
            $pl->hen_count = 2;
            $pl->hdep = 50.0;
            $pl->recorded_by = $this->user->id;
            $pl->save();
        }

        MortalityLog::create([
            'cage_id'     => $this->cage->id,
            'log_date'    => now()->subDay()->toDateString(),
            'count'       => 1,
            'reason'      => 'Disease',
            'recorded_by' => $this->user->id,
        ]);

        FeedConsumptionLog::create([
            'cage_id'          => $this->cage->id,
            'feed_batch_id'    => $this->feedBatch->id,
            'log_date'         => now()->subDay()->toDateString(),
            'feed_consumed_kg' => 5.0,
            'recorded_by'      => $this->user->id,
        ]);

        EnvironmentalLog::create([
            'cage_id'       => $this->cage->id,
            'recorded_at'   => now()->subDay(),
            'temperature_c' => 28.0,
            'humidity_pct'  => 65.0,
        ]);
    }

    public function test_standard_delete_without_preserving_removes_everything(): void
    {
        $this->actingAs($this->user)
            ->delete("/cages/{$this->cage->id}", [
                'hens_action'          => 'delete',
                'return_sensors'       => 0,
                'preserve_production'  => 0,
                'preserve_mortality'   => 0,
                'preserve_feed'        => 0,
                'preserve_environment' => 0,
            ]);

        $this->assertNull(Cage::find($this->cage->id));
        $this->assertEquals(0, CageSlot::where('cage_id', $this->cage->id)->count());

        $slotIds = $this->cage->cageSlots->pluck('id');
        $this->assertEquals(0, ProductionLog::whereIn('cage_slot_id', $slotIds)->count());
        $this->assertEquals(0, MortalityLog::where('cage_id', $this->cage->id)->count());
        $this->assertEquals(0, FeedConsumptionLog::where('cage_id', $this->cage->id)->count());
        $this->assertEquals(0, EnvironmentalLog::where('cage_id', $this->cage->id)->count());
    }

    public function test_standard_delete_preserving_mortality_only(): void
    {
        $this->actingAs($this->user)
            ->delete("/cages/{$this->cage->id}", [
                'hens_action'          => 'delete',
                'return_sensors'       => 0,
                'preserve_production'  => 0,
                'preserve_mortality'   => 1,
                'preserve_feed'        => 0,
                'preserve_environment' => 0,
            ]);

        $this->assertNull(Cage::find($this->cage->id));
        $this->assertEquals(0, CageSlot::where('cage_id', $this->cage->id)->count());

        // Mortality survives with cage_id = null
        $survivor = MortalityLog::whereNull('cage_id')
            ->where('log_date', now()->subDay()->toDateString())
            ->where('reason', 'Disease')
            ->first();
        $this->assertNotNull($survivor);

        // Other logs are cascade-deleted
        $slotIds = $this->cage->cageSlots->pluck('id');
        $this->assertEquals(0, ProductionLog::whereIn('cage_slot_id', $slotIds)->count());
        $this->assertEquals(0, FeedConsumptionLog::where('cage_id', $this->cage->id)->count());
        $this->assertEquals(0, EnvironmentalLog::where('cage_id', $this->cage->id)->count());
    }

    public function test_force_delete_removes_everything_without_salvage(): void
    {
        $slotIds = $this->cage->cageSlots->pluck('id')->toArray();

        $this->actingAs($this->user)
            ->delete("/cages/{$this->cage->id}/force")
            ->assertRedirect('/cages');

        $this->assertNull(Cage::find($this->cage->id));
        $this->assertEquals(0, CageSlot::where('cage_id', $this->cage->id)->count());
        $this->assertEquals(0, ProductionLog::whereIn('cage_slot_id', $slotIds)->count());
    }

    public function test_standard_delete_with_move_makes_active_hens_unplaced(): void
    {
        $this->actingAs($this->user)
            ->delete("/cages/{$this->cage->id}", [
                'hens_action'          => 'move',
                'return_sensors'       => 0,
                'preserve_production'  => 0,
                'preserve_mortality'   => 0,
                'preserve_feed'        => 0,
                'preserve_environment' => 0,
            ]);

        $unplacedCount = Hen::whereNull('cage_slot_id')->where('is_active', 1)->count();

        $this->assertGreaterThan(0, $unplacedCount);
        $this->assertEquals(
            $this->activeHenCount,
            $unplacedCount,
            'All active hens should be unplaced after move'
        );
    }

    public function test_active_hens_nulled_inactive_hens_deleted_when_cage_deleted(): void
    {
        $slotIds = CageSlot::where('cage_id', $this->cage->id)->pluck('id')->toArray();
        if (empty($slotIds)) {
            $msg = sprintf(
                'Cage #%d (%s): no slots found. Total cages: %d, total slots: %d, total active hens: %d',
                $this->cage->id,
                $this->cage->cage_code,
                Cage::count(),
                CageSlot::count(),
                Hen::where('is_active', 1)->count()
            );
            $this->fail($msg);
        }

        $activeCountBefore = Hen::whereIn('cage_slot_id', $slotIds)->where('is_active', 1)->count();
        $inactiveCountBefore = Hen::whereIn('cage_slot_id', $slotIds)->where('is_active', 0)->count();

        $this->assertGreaterThan(0, $activeCountBefore);
        $this->assertEquals(1, $inactiveCountBefore);

        $this->actingAs($this->user)
            ->delete("/cages/{$this->cage->id}", [
                'hens_action'          => 'move',
                'return_sensors'       => 0,
                'preserve_production'  => 0,
                'preserve_mortality'   => 0,
                'preserve_feed'        => 0,
                'preserve_environment' => 0,
            ]);

        // Active hens survive with null cage_slot_id (moved to unplaced)
        $unplacedActive = Hen::whereNull('cage_slot_id')->where('is_active', 1)->count();
        $this->assertGreaterThanOrEqual($activeCountBefore, $unplacedActive,
            'Active hens should survive as unplaced after cage deletion');

        // Inactive hens: cascade-deleted via FK
        $remainingInactive = Hen::where('is_active', 0)->whereIn('cage_slot_id', $slotIds)->count();
        $this->assertEquals(0, $remainingInactive,
            'Inactive hens referencing deleted slots should be cascade-deleted');
    }
}
