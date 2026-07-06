<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\MortalityLogHen;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0/P1 Core invariants: occupancy never exceeds capacity and always
 * matches COUNT(active_hens) after placement, removal, culling, and moving.
 */
class OccupancyInvariantsTest extends TestCase
{
    private User $user;
    private Cage $cage;
    private Cage $emptyCage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'admin@layrate.local')->first();
        $this->cage = Cage::where('cage_code', 'CAGE-A')->first();
        $this->emptyCage = Cage::where('cage_code', 'CAGE-D')->first();
    }

    public function test_occupancy_matches_active_hens_after_seeding(): void
    {
        foreach ($this->cage->cageSlots as $slot) {
            $expected = Hen::where('cage_slot_id', $slot->id)
                ->where('is_active', 1)
                ->count();
            $this->assertEquals(
                $expected,
                $slot->fresh()->current_occupancy,
                "Slot #{$slot->slot_number}: occupancy {$slot->fresh()->current_occupancy} != COUNT(active) {$expected}"
            );
        }
    }

    public function test_occupancy_matches_active_hens_after_manual_placement(): void
    {
        $slot = $this->emptyCage->cageSlots->first(fn($s) => $s->remaining > 0);
        if (!$slot) {
            $this->markTestSkipped('No slots with remaining capacity');
        }

        $hen = $this->unplacedHen();

        DB::transaction(function () use ($hen, $slot) {
            $hen->cage_slot_id = $slot->id;
            $hen->placement_date = today();
            $hen->save();
            $slot->increment('current_occupancy');
        });

        $expected = Hen::where('cage_slot_id', $slot->id)->where('is_active', 1)->count();
        $this->assertEquals($expected, $slot->fresh()->current_occupancy);
    }

    public function test_occupancy_matches_active_hens_after_mortality(): void
    {
        $slot = $this->cage->cageSlots->first(fn($s) => $s->current_occupancy > 0);

        $hen = Hen::where('cage_slot_id', $slot->id)->where('is_active', 1)->first();

        DB::transaction(function () use ($hen, $slot) {
            $hen->update(['is_active' => false]);
            $slot->decrement('current_occupancy');

            $log = MortalityLog::create([
                'cage_id'     => $this->cage->id,
                'log_date'    => today(),
                'count'       => 1,
                'reason'      => 'Disease',
                'recorded_by' => $this->user->id,
            ]);
            $pivot = new MortalityLogHen;
            $pivot->mortality_log_id = $log->id;
            $pivot->hen_id = $hen->id;
            $pivot->cage_slot_id = $slot->id;
            $pivot->save();
        });

        $expected = Hen::where('cage_slot_id', $slot->id)->where('is_active', 1)->count();
        $this->assertEquals($expected, $slot->fresh()->current_occupancy);
    }

    public function test_occupancy_never_exceeds_capacity(): void
    {
        $fullSlot = $this->cage->cageSlots->first(
            fn($s) => $s->current_occupancy >= $this->cage->max_chickens_per_slot
        );

        $henIds = [];
        for ($i = 0; $i < 3; $i++) {
            $henIds[] = $this->unplacedHen()->id;
        }

        $response = $this->actingAs($this->user)
            ->post('/cages/bulk-add', [
                'hen_ids'  => implode(',', $henIds),
                'cage_id'  => $this->cage->id,
                'mode'     => 'manual',
                'slot_ids' => (string) $fullSlot->id,
            ]);

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(
            $fullSlot->current_occupancy,
            $fullSlot->fresh()->current_occupancy,
            'Occupancy changed after failed placement'
        );
    }

    public function test_lock_for_update_is_used_in_bulk_add_transaction(): void
    {
        DB::enableQueryLog();

        $hen = $this->unplacedHen();
        $slot = $this->emptyCage->cageSlots->first(fn($s) => $s->remaining > 0);
        if (!$slot) {
            $this->markTestSkipped('No slots with remaining capacity');
        }

        $this->actingAs($this->user)
            ->post('/cages/bulk-add', [
                'hen_ids'  => (string) $hen->id,
                'cage_id'  => $this->emptyCage->id,
                'mode'     => 'manual',
                'slot_ids' => (string) $slot->id,
            ]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $hasLock = false;
        foreach ($queries as $q) {
            if (str_contains(strtolower($q['query']), 'for update')) {
                $hasLock = true;
                break;
            }
        }

        $this->assertTrue($hasLock, 'Expected lockForUpdate() in bulk-add transaction');
    }

    public function test_bulk_remove_mixed_placed_and_unplaced(): void
    {
        $placedHen = Hen::whereNotNull('cage_slot_id')
            ->where('is_active', 1)
            ->whereHas('cageSlot', fn($q) => $q->where('cage_id', $this->cage->id))
            ->first();

        $unplacedHen = $this->unplacedHen();

        $response = $this->actingAs($this->user)
            ->post('/chickens/remove', [
                'hen_ids'          => "{$placedHen->id},{$unplacedHen->id}",
                'record_mortality' => '1',
                'reason'           => 'Disease',
                'notes'            => 'Mixed placed/unplaced remove',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $msg = session('success');
        $this->assertStringContainsString('2 hen(s) removed', $msg);
        $this->assertStringContainsString('1 recorded as mortality', $msg);
        $this->assertStringContainsString('1 unplaced hen(s) skipped mortality', $msg);

        $this->assertFalse(Hen::find($placedHen->id)->is_active);
        $this->assertFalse(Hen::find($unplacedHen->id)->is_active);

        $latestMortality = MortalityLog::latest()->first();
        $this->assertEquals(1, $latestMortality->count);
    }

    public function test_re_validation_inside_lock_rejects_already_full_slot(): void
    {
        $henIds = [];
        for ($i = 0; $i < 2; $i++) {
            $henIds[] = $this->unplacedHen()->id;
        }

        $fullSlot = $this->cage->cageSlots->first(
            fn($s) => $s->current_occupancy >= $this->cage->max_chickens_per_slot
        );

        $response = $this->actingAs($this->user)
            ->post('/cages/bulk-add', [
                'hen_ids'  => implode(',', $henIds),
                'cage_id'  => $this->cage->id,
                'mode'     => 'manual',
                'slot_ids' => (string) $fullSlot->id,
            ]);

        $this->assertTrue($response->isRedirect());

        foreach ($henIds as $id) {
            $hen = Hen::find($id);
            $this->assertNull($hen->cage_slot_id);
            $this->assertTrue((bool) $hen->is_active);
        }
    }

    private function unplacedHen(): Hen
    {
        $s = substr(uniqid(), -8);
        $hen = new Hen;
        $hen->chicken_id = "OT-{$s}";
        $hen->breed = 'ISA Brown';
        $hen->tag_code = "OT-{$s}";
        $hen->date_acquired = now()->subDays(30);
        $hen->flock_age_weeks = 20;
        $hen->is_active = 1;
        $hen->save();
        return $hen;
    }
}
