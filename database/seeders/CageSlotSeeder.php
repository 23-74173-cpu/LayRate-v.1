<?php

namespace Database\Seeders;

use App\Models\Cage;
use App\Models\CageSlot;
use Illuminate\Database\Seeder;

/**
 * UTILITY — generates cage_slot rows for all existing cages.
 *
 * Not called by DatabaseSeeder.  Run this ad-hoc when you need
 * to populate the cage_slots table for existing cages (e.g. after
 * a corrective schema migration or a fresh import of cage records
 * without their slot children).
 *
 * Idempotent: uses firstOrCreate.  Slots are created with 0 occupancy.
 */
class CageSlotSeeder extends Seeder
{
    /**
     * ╔══════════════════════════════════════════════════════════════════╗
     * ║  Corrective seeder — generates cage_slots for all 4 real cages  ║
     * ║                                                                  ║
     * ║  The production cage_slots table has ZERO rows for the 4 real    ║
     * ║  cages (CAGE-A through CAGE-D). The slot grid UI expects each    ║
     * ║  cage to have `rows × slots_per_row` CageSlot records, so the   ║
     * ║  grid renders as empty without them.                            ║
     * ║                                                                  ║
     * ║  ⚠️  This uses whatever `rows` and `slots_per_row` values are   ║
     * ║  currently set on the Cage records.  If you ran the migration   ║
     * ║  first (which sets placeholder defaults), those placeholder      ║
     * ║  values will determine the grid dimensions.  Slots created with ║
     * ║  wrong dimensions will need to be deleted and re-created after   ║
     * ║  the real farm layout is entered via the Edit Cage modal.       ║
     * ╚══════════════════════════════════════════════════════════════════╝
     */
    public function run(): void
    {
        $cages = Cage::all();

        if ($cages->isEmpty()) {
            $this->command->warn('No cages found — nothing to seed.');

            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($cages as $cage) {
            $totalSlots = $cage->rows * $cage->slots_per_row;

            $this->command->info(
                "Processing {$cage->cage_code}: {$cage->rows}×{$cage->slots_per_row} = {$totalSlots} slots"
            );

            for ($row = 1; $row <= $cage->rows; $row++) {
                for ($col = 1; $col <= $cage->slots_per_row; $col++) {
                    $slotNumber = ($row - 1) * $cage->slots_per_row + $col;

                    $slot = CageSlot::firstOrCreate(
                        ['cage_id' => $cage->id, 'slot_number' => $slotNumber],
                        [
                            'row_number' => $row,
                            'column_number' => $col,
                            'current_occupancy' => 0,
                        ]
                    );

                    if ($slot->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        $this->command->info("Done — {$created} slots created, {$skipped} already existed.");
    }
}
