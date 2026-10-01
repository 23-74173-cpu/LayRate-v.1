<?php

namespace Tests\Concerns;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\FeedBatch;
use App\Models\Hen;

/**
 * Test-only baseline farm data (deployment-safe: never touches production
 * seeders). DatabaseSeeder intentionally creates no cages, so suites that
 * assume the standard demo cages fail in setUp with ModelNotFoundException.
 * Use these helpers instead of depending on DemoDataSeeder (which also
 * populates hens only when slots pre-exist — a classic ordering trap).
 */
trait SeedsBaselineFarm
{
    /**
     * Standard demo cages CAGE-A..C (active) + CAGE-D (inactive).
     *
     * @return array<string, Cage> keyed by cage_code
     */
    protected function seedBaselineCages(): array
    {
        $defs = [
            'CAGE-A' => ['rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 1],
            'CAGE-B' => ['rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 1],
            'CAGE-C' => ['rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 1],
            'CAGE-D' => ['rows' => 3, 'slots_per_row' => 5, 'max_chickens_per_slot' => 4, 'total_capacity' => 60, 'is_active' => 0],
        ];
        $cages = [];
        foreach ($defs as $code => $attrs) {
            $cages[$code] = Cage::firstOrCreate(['cage_code' => $code], $attrs + ['location' => '']);
        }

        return $cages;
    }

    /**
     * rows × slots_per_row CageSlot records for a cage (mirrors
     * CageSlotSeeder, but scoped to one cage and safe to call any time).
     */
    protected function seedBaselineSlots(Cage $cage): void
    {
        for ($row = 1; $row <= $cage->rows; $row++) {
            for ($col = 1; $col <= $cage->slots_per_row; $col++) {
                $slotNumber = ($row - 1) * $cage->slots_per_row + $col;
                CageSlot::firstOrCreate(
                    ['cage_id' => $cage->id, 'slot_number' => $slotNumber],
                    ['row_number' => $row, 'column_number' => $col, 'current_occupancy' => 0]
                );
            }
        }
    }

    protected function seedBaselineFeedBatch(): FeedBatch
    {
        return FeedBatch::firstOrCreate(
            ['batch_code' => 'F-TEST'],
            ['brand' => 'Test Mash', 'crude_protein' => 17.5, 'total_quantity_kg' => 100, 'date_received' => '2026-01-01']
        );
    }

    /**
     * One active hen placed in the cage's first slot (creates slots if needed).
     */
    protected function placeBaselineHen(Cage $cage, string $tag = 'TEST-HEN-001'): Hen
    {
        $this->seedBaselineSlots($cage->fresh());
        $slot = CageSlot::where('cage_id', $cage->id)->orderBy('slot_number')->firstOrFail();

        return $this->makeHenInSlot($slot, $tag);
    }

    /**
     * An active hen with no slot (unplaced inventory).
     */
    protected function makeUnplacedHen(string $tag = 'TEST-UNPLACED-001'): Hen
    {
        return Hen::firstOrCreate(
            ['tag_code' => $tag],
            [
                'breed' => 'ISA Brown', 'flock_age_weeks' => 28,
                'date_acquired' => '2026-01-01', 'placement_date' => '2026-01-01',
                'age_at_placement_weeks' => 0, 'is_active' => 1,
            ]
        );
    }

    /**
     * Fill every slot of a cage with $hensPerSlot hens and set occupancy to
     * match (realistic occupied state for invariant/ajax suites).
     */
    protected function seedOccupiedSlots(Cage $cage, int $hensPerSlot = 2): void
    {
        $this->seedBaselineSlots($cage->fresh());
        $slots = CageSlot::where('cage_id', $cage->id)->orderBy('slot_number')->get();
        foreach ($slots as $slot) {
            for ($h = 1; $h <= $hensPerSlot; $h++) {
                $this->makeHenInSlot($slot, "TEST-{$cage->cage_code}-S{$slot->slot_number}-H{$h}");
            }
            $slot->update(['current_occupancy' => $hensPerSlot]);
        }
    }

    protected function makeHenInSlot(CageSlot $slot, string $tag): Hen
    {
        $hen = Hen::firstOrCreate(
            ['tag_code' => $tag],
            [
                'breed' => 'ISA Brown', 'flock_age_weeks' => 28,
                'date_acquired' => '2026-01-01', 'placement_date' => '2026-01-01',
                'age_at_placement_weeks' => 0, 'is_active' => 1,
            ]
        );
        // cage_slot_id is not fillable — assign directly like DemoDataSeeder.
        if ($hen->wasRecentlyCreated) {
            $hen->cage_slot_id = $slot->id;
            $hen->save();
        }

        return $hen;
    }

    protected function seedBaselineMortalityLog(Cage $cage, ?int $recordedBy = null): \App\Models\MortalityLog
    {
        $attrs = ['count' => 1, 'reason' => 'Unknown', 'notes' => 'Baseline test log'];
        if ($recordedBy !== null) {
            $attrs['recorded_by'] = $recordedBy;
        }

        return \App\Models\MortalityLog::firstOrCreate(
            ['cage_id' => $cage->id, 'log_date' => now()->toDateString()],
            $attrs
        );
    }
}
