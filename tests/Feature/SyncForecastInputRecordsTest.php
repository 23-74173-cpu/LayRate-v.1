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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SyncForecastInputRecords used to run 4 fresh queries per (cage, date)
 * group inside its main loop. Rewritten to batch all 4 into one query each,
 * executed once before the loop. This test seeds two cages across two
 * dates, deliberately including: two active hens in the same cage (to check
 * the "first hen" pick is deterministic, not that it changed to something
 * else), and two feed_consumption_logs on the same cage+date (to check the
 * original "first row, not sum" semantic survived the batching — this is
 * the one field where a careless rewrite could silently start summing
 * instead of picking one row).
 */
class SyncForecastInputRecordsTest extends TestCase
{
    use RefreshDatabase;

    private Cage $cageA;
    private Cage $cageB;
    private CageSlot $slotA;
    private CageSlot $slotB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cageA = Cage::create(['cage_code' => 'CAGE-SYNC-A', 'location' => 'Test', 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 10, 'total_capacity' => 10, 'is_active' => 1]);
        $this->cageB = Cage::create(['cage_code' => 'CAGE-SYNC-B', 'location' => 'Test', 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 10, 'total_capacity' => 10, 'is_active' => 1]);
        $this->slotA = CageSlot::create(['cage_id' => $this->cageA->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 4]);
        $this->slotB = CageSlot::create(['cage_id' => $this->cageB->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 4]);

        $day1 = now()->subDays(2)->toDateString();
        $day2 = now()->subDay()->toDateString();

        // Production logs — the outer query's (cage, date) universe.
        ProductionLog::create(['cage_slot_id' => $this->slotA->id, 'log_date' => $day1, 'egg_count' => 10, 'hen_count' => 4, 'hdep' => 50]);
        ProductionLog::create(['cage_slot_id' => $this->slotA->id, 'log_date' => $day2, 'egg_count' => 8, 'hen_count' => 4, 'hdep' => 40]);
        ProductionLog::create(['cage_slot_id' => $this->slotB->id, 'log_date' => $day1, 'egg_count' => 6, 'hen_count' => 4, 'hdep' => 30]);

        // Two active hens in cage A — deterministic pick must be the
        // lower-id one (h1), not whichever the old un-ordered query happened
        // to return.
        $h1 = new Hen(['tag_code' => 'SYNC-A-1', 'breed' => 'ISA Brown', 'flock_age_weeks' => 20, 'date_acquired' => now()->subMonths(6), 'placement_date' => now()->subMonths(6), 'age_at_placement_weeks' => 0, 'is_active' => 1]);
        $h1->cage_slot_id = $this->slotA->id;
        $h1->save();
        $h2 = new Hen(['tag_code' => 'SYNC-A-2', 'breed' => 'Hy-Line Brown', 'flock_age_weeks' => 22, 'date_acquired' => now()->subMonths(6), 'placement_date' => now()->subMonths(6), 'age_at_placement_weeks' => 0, 'is_active' => 1]);
        $h2->cage_slot_id = $this->slotA->id;
        $h2->save();

        $hb = new Hen(['tag_code' => 'SYNC-B-1', 'breed' => 'Dekalb White', 'flock_age_weeks' => 25, 'date_acquired' => now()->subMonths(6), 'placement_date' => now()->subMonths(6), 'age_at_placement_weeks' => 0, 'is_active' => 1]);
        $hb->cage_slot_id = $this->slotB->id;
        $hb->save();

        // Environmental readings — cage A, day1: avg of 30 and 20 = 25.
        EnvironmentalLog::create(['cage_id' => $this->cageA->id, 'recorded_at' => $day1 . ' 06:00:00', 'temperature_c' => 30.0, 'humidity_pct' => 60.0]);
        EnvironmentalLog::create(['cage_id' => $this->cageA->id, 'recorded_at' => $day1 . ' 18:00:00', 'temperature_c' => 20.0, 'humidity_pct' => 50.0]);

        // Feed logs — cage A, day1: TWO rows. Original code's ->first()
        // picks one arbitrarily (no aggregate); this must still pick exactly
        // one row's value, not sum them (10 + 3 = 13, which must NOT appear).
        $batch = FeedBatch::create(['batch_code' => 'FB-SYNC-1', 'brand' => 'Test', 'crude_protein' => 18.0, 'total_quantity_kg' => 100, 'unit_cost' => 50, 'date_received' => now()->subDays(10)]);
        FeedConsumptionLog::create(['cage_id' => $this->cageA->id, 'feed_batch_id' => $batch->id, 'log_date' => $day1, 'feed_consumed_kg' => 10.0]);
        FeedConsumptionLog::create(['cage_id' => $this->cageA->id, 'feed_batch_id' => $batch->id, 'log_date' => $day1, 'feed_consumed_kg' => 3.0]);

        // Mortality — cage A, day1: two rows, genuinely summed (2 + 1 = 3).
        MortalityLog::create(['cage_id' => $this->cageA->id, 'log_date' => $day1, 'count' => 2, 'reason' => 'Disease']);
        MortalityLog::create(['cage_id' => $this->cageA->id, 'log_date' => $day1, 'count' => 1, 'reason' => 'Injury']);
    }

    public function test_sync_produces_correct_records_for_every_cage_date_combination(): void
    {
        $this->artisan('forecast:sync-input-records')->assertExitCode(0);

        // Only cage A on day1 has environmental logs. Rows without env data
        // (cage A day2, cage B day1) must be skipped — a forecast input row
        // must contain temperature and humidity.
        $this->assertDatabaseCount('forecast_input_records', 1);

        $day1 = now()->subDays(2)->toDateString();
        $day2 = now()->subDay()->toDateString();

        $rowA1 = DB::table('forecast_input_records')->where('cage_code', 'CAGE-SYNC-A')->where('date', $day1)->first();
        $this->assertNotNull($rowA1);
        $this->assertEquals(10, $rowA1->egg_count);
        $this->assertEquals('ISA Brown', $rowA1->breed, 'Must pick the lower-id hen (h1) deterministically.');
        $this->assertEquals(25.0, (float) $rowA1->temperature_c, '', 0.01); // AVG(30, 20)
        $this->assertEquals(55.0, (float) $rowA1->humidity_percent, '', 0.01); // AVG(60, 50)
        $this->assertContains((float) $rowA1->feed_consumed_kg, [10.0, 3.0], 'Must be ONE of the two feed rows, not their sum (13.0).');
        $this->assertNotEquals(13.0, (float) $rowA1->feed_consumed_kg, 'Feed value must not silently become a SUM.');
        $this->assertEquals(3, $rowA1->mortality_count, 'Mortality genuinely sums: 2 + 1 = 3.');

        // Rows whose (cage, date) has no environmental logs are omitted.
        $this->assertDatabaseMissing('forecast_input_records', ['cage_code' => 'CAGE-SYNC-A', 'date' => $day2]);
        $this->assertDatabaseMissing('forecast_input_records', ['cage_code' => 'CAGE-SYNC-B', 'date' => $day1]);
        $this->assertDatabaseMissing('forecast_input_records', ['cage_code' => 'CAGE-SYNC-B', 'date' => $day2]);
    }

    public function test_dry_run_makes_no_database_changes(): void
    {
        $this->artisan('forecast:sync-input-records', ['--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseCount('forecast_input_records', 0);
    }

    public function test_cage_filter_option_scopes_to_one_cage(): void
    {
        $this->artisan('forecast:sync-input-records', ['--cage' => 'CAGE-SYNC-A'])->assertExitCode(0);

        $this->assertDatabaseCount('forecast_input_records', 1);
        $this->assertDatabaseHas('forecast_input_records', ['cage_code' => 'CAGE-SYNC-A']);
        $this->assertNotEquals(0, DB::table('forecast_input_records')->count());
    }

    public function test_cage_without_environmental_data_skips_all_rows(): void
    {
        // Cage B has no environmental logs, so filtering to it must insert nothing.
        $this->artisan('forecast:sync-input-records', ['--cage' => 'CAGE-SYNC-B'])->assertExitCode(0);
        $this->assertDatabaseCount('forecast_input_records', 0);
    }
}
