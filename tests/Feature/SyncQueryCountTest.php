<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\ProductionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression guard for the N+1 fix in SyncForecastInputRecords: the command
 * used to run 4 fresh queries per (cage, date) group inside its main loop
 * (~4N+2 total). Verified via git-stash comparison against the
 * pre-optimization code on this exact scenario: old = 122 queries for 30
 * groups, new = 6. If this regresses back toward O(N), it'll show up here
 * long before anyone notices it in a slow scheduled command on the Pi.
 */
class SyncQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_count_stays_constant_regardless_of_cage_date_group_count(): void
    {
        // 3 cages x 10 days = 30 (cage, date) groups.
        for ($c = 1; $c <= 3; $c++) {
            $cage = Cage::create(['cage_code' => "QC-{$c}", 'location' => 'T', 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 10, 'total_capacity' => 10, 'is_active' => 1]);
            $slot = CageSlot::create(['cage_id' => $cage->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 4]);
            for ($d = 1; $d <= 10; $d++) {
                ProductionLog::create(['cage_slot_id' => $slot->id, 'log_date' => now()->subDays($d)->toDateString(), 'egg_count' => 5, 'hen_count' => 4, 'hdep' => 50]);
            }
        }

        DB::enableQueryLog();
        $this->artisan('forecast:sync-input-records')->assertExitCode(0);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            15,
            $count,
            "Query count is {$count} for 30 (cage,date) groups — expected ~6 (constant, batched). ".
            "If this scales with group count again (was 122 pre-fix), the N+1 has regressed."
        );
    }
}
