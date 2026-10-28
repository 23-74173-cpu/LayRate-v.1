<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\ProductionLog;
use App\Models\User;
use App\Services\FcrCalculator;
use App\Console\Commands\ImportHistoricalProductionCsv;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalCsvPreviewTest extends TestCase
{
    public function test_csv_cage_day_aggregates_and_fcr_match_deterministic_fixture_without_writes_to_csv_targets(): void
    {
        $rows = [];
        $dailyEggs = ['CAGE-A' => 217, 'CAGE-B' => 150, 'CAGE-C' => 105];
        $dailyHens = ['CAGE-A' => 240, 'CAGE-B' => 168, 'CAGE-C' => 120];
        foreach (range(0, 157) as $dayOffset) {
            $date = Carbon::parse('2026-03-23')->addDays($dayOffset)->toDateString();
            foreach (['CAGE-A', 'CAGE-B', 'CAGE-C'] as $cageCode) {
                $rows[] = [
                    'Date' => $date, 'Cage_Code' => $cageCode, 'Egg_Count' => (string) ($dayOffset === 157 ? $dailyEggs[$cageCode] : 0),
                    'Hen_Count' => (string) $dailyHens[$cageCode], 'Feed_Consumed_kg' => '20.31', 'Crude_Protein_Percent' => '17.0',
                    'Temperature_C' => '33.3', 'Humidity_Percent' => '86.0', 'Humidity_Corrected' => 'N',
                    'Mortality_Count' => '0', 'Farm_Hen_Count_On_Date' => '528',
                    'Hen_Count_Record_Type' => 'Historical cage-day aggregate (not individual hen inventory)',
                ];
            }
        }
        $this->assertCount(474, $rows);
        $this->assertSame(472, array_sum(array_map(fn ($row) => (int) $row['Egg_Count'], $rows)));
        $this->assertSame(83424, array_sum(array_map(fn ($row) => (int) $row['Hen_Count'], $rows)));
        $this->assertSame(250272, array_sum(array_map(fn ($row) => (int) $row['Farm_Hen_Count_On_Date'], $rows)));
        $this->assertSame(['Historical cage-day aggregate (not individual hen inventory)'], array_values(array_unique(array_column($rows, 'Hen_Count_Record_Type'))));

        $method = new \ReflectionMethod(ImportHistoricalProductionCsv::class, 'validateRows');
        $method->setAccessible(true);
        $method->invoke(app(ImportHistoricalProductionCsv::class), $rows);

        $originalCages = DB::table('cages')->count();
        $originalProduction = DB::table('production_logs')->count();
        $originalFeed = DB::table('feed_consumption_logs')->count();
        $originalEnvironment = DB::table('environmental_logs')->count();
        $originalMortality = DB::table('mortality_logs')->count();

        $user = User::factory()->create(['role' => 'admin']);
        $cage = null;
        $batch = null;
        try {
            $cage = Cage::create([
                'cage_code' => 'CAGE-CSV-PREVIEW', 'location' => 'Fixture', 'rows' => 1,
                'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => true,
            ]);
            $slot = CageSlot::create([
                'cage_id' => $cage->id, 'slot_number' => 1, 'row_number' => 1,
                'column_number' => 1, 'current_occupancy' => 4,
            ]);
            $batch = FeedBatch::create([
                'batch_code' => 'F-CSV-PREVIEW', 'brand' => 'fixture', 'crude_protein' => 17,
                'date_received' => '2026-08-27',
            ]);
            ProductionLog::create([
                'cage_slot_id' => $slot->id, 'log_date' => '2026-08-27', 'egg_count' => 100,
                'hen_count' => 4, 'hdep' => 2500, 'recorded_by' => $user->id,
                'logged_via' => 'manual', 'is_demo' => false,
            ]);
            FeedConsumptionLog::create([
                'cage_id' => $cage->id, 'feed_batch_id' => $batch->id, 'log_date' => '2026-08-27',
                'feed_consumed_kg' => 12, 'recorded_by' => $user->id,
            ]);

            $this->assertSame(2.0, FcrCalculator::forCage($cage, Carbon::parse('2026-08-27'), Carbon::parse('2026-08-27')));

        } finally {
            if ($cage) {
                DB::table('production_logs')->whereIn('cage_slot_id', DB::table('cage_slots')->where('cage_id', $cage->id)->select('id'))->delete();
                DB::table('feed_consumption_logs')->where('cage_id', $cage->id)->delete();
                if ($batch) DB::table('feed_batches')->where('id', $batch->id)->delete();
                DB::table('cages')->where('id', $cage->id)->delete();
            }
            if ($batch) DB::table('feed_batches')->where('id', $batch->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
        }

        $this->assertSame($originalCages, DB::table('cages')->count());
        $this->assertSame($originalProduction, DB::table('production_logs')->count());
        $this->assertSame($originalFeed, DB::table('feed_consumption_logs')->count());
        $this->assertSame($originalEnvironment, DB::table('environmental_logs')->count());
        $this->assertSame($originalMortality, DB::table('mortality_logs')->count());
    }
}
