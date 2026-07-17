<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggStockBatch;
use App\Models\EnvironmentalLog;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cageA;
    private Cage $cageB;
    private CageSlot $slotA1;
    private CageSlot $slotB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->cageA = Cage::create([
            'cage_code' => 'CAGE-A',
            'location' => 'North',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $this->cageB = Cage::create([
            'cage_code' => 'CAGE-B',
            'location' => 'South',
            'rows' => 1,
            'slots_per_row' => 2,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 8,
            'is_active' => 1,
        ]);

        $this->slotA1 = CageSlot::create([
            'cage_id' => $this->cageA->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 4,
        ]);

        $this->slotB1 = CageSlot::create([
            'cage_id' => $this->cageB->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 4,
        ]);
    }

    private function createHen(CageSlot $slot, string $breed, bool $active, string $tag): Hen
    {
        $hen = Hen::create([
            'tag_code' => $tag,
            'breed' => $breed,
            'flock_age_weeks' => 28,
            'date_acquired' => now()->subMonths(6)->toDateString(),
            'placement_date' => now()->subMonths(6)->toDateString(),
            'age_at_placement_weeks' => 0,
            'is_active' => $active ? 1 : 0,
        ]);
        $hen->cage_slot_id = $slot->id;
        $hen->save();

        return $hen;
    }

    private function createProductionLog(CageSlot $slot, int $eggs, string $date): ProductionLog
    {
        $log = new ProductionLog();
        $log->cage_slot_id = $slot->id;
        $log->log_date = $date;
        $log->egg_count = $eggs;
        $log->hen_count = 4;
        $log->hdep = round(($eggs / 4) * 100, 2);
        $log->logged_via = 'manual';
        $log->recorded_by = $this->user->id;
        $log->save();

        return $log;
    }

    /** @test */
    public function reports_page_loads_without_parameters()
    {
        $response = $this->actingAs($this->user)->get(route('reports'));

        $response->assertOk();
        $response->assertSee('Generate Report');
    }

    /** @test */
    public function production_report_renders_rows_and_summary()
    {
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');
        $this->createProductionLog($this->slotA1, 4, now()->subDays(2)->toDateString());
        $this->createProductionLog($this->slotA1, 3, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'production',
            'from' => now()->subDays(3)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('Total Eggs');
        $response->assertSee('7'); // 4 + 3
        $response->assertSee('ISA Brown');
    }

    /**
     * Regression test for item #93: the Production report must attribute the
     * breed of an ACTIVE hen, not whichever hen happens to come first in an
     * unfiltered collection. The inactive hen is created first (lower id) so
     * an unfiltered ->first() would wrongly pick it.
     *
     * @test
     */
    public function production_report_shows_active_hen_breed_not_inactive_predecessor()
    {
        $this->createHen($this->slotA1, 'Hy-Line Brown', false, 'A-OLD1'); // replaced hen
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');     // current hen
        $this->createProductionLog($this->slotA1, 4, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'production',
            'from' => now()->subDays(2)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('ISA Brown');
        $response->assertDontSee('Hy-Line Brown');
    }

    /**
     * Regression test for item #94 (part 1): the Egg Stock report type exists,
     * and "All Cages" includes farm-level batches whose cage_id is NULL.
     *
     * @test
     */
    public function egg_stock_report_all_cages_includes_null_cage_batches()
    {
        EggStockBatch::create(['egg_size' => 'large', 'count' => 333, 'harvested_date' => now()->subDays(3)->toDateString(), 'cage_id' => $this->cageA->id]);
        EggStockBatch::create(['egg_size' => 'medium', 'count' => 444, 'harvested_date' => now()->subDays(2)->toDateString(), 'cage_id' => $this->cageB->id]);
        EggStockBatch::create(['egg_size' => 'large', 'count' => 555, 'harvested_date' => now()->subDay()->toDateString(), 'cage_id' => null]);

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'egg_stock',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('Total Stocked');
        $response->assertSee('1332'); // 333 + 444 + 555 — NULL-cage batch included
        $response->assertSee('333');
        $response->assertSee('444');
        $response->assertSee('555');
    }

    /**
     * Regression test for item #94 (part 2): a specific cage filter excludes
     * farm-level (NULL-cage) batches and other cages' batches.
     *
     * @test
     */
    public function egg_stock_report_specific_cage_excludes_farm_level_batches()
    {
        EggStockBatch::create(['egg_size' => 'large', 'count' => 333, 'harvested_date' => now()->subDays(3)->toDateString(), 'cage_id' => $this->cageA->id]);
        EggStockBatch::create(['egg_size' => 'medium', 'count' => 444, 'harvested_date' => now()->subDays(2)->toDateString(), 'cage_id' => $this->cageB->id]);
        EggStockBatch::create(['egg_size' => 'large', 'count' => 555, 'harvested_date' => now()->subDay()->toDateString(), 'cage_id' => null]);

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'egg_stock',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'CAGE-A',
        ]));

        $response->assertOk();
        $response->assertSee('333');
        $response->assertDontSee('444');
        $response->assertDontSee('555');
    }

    /** @test */
    public function egg_stock_csv_export_streams_correct_rows()
    {
        EggStockBatch::create(['egg_size' => 'large', 'count' => 333, 'harvested_date' => now()->subDay()->toDateString(), 'cage_id' => $this->cageA->id]);

        $response = $this->actingAs($this->user)->get(route('reports.csv', [
            'type' => 'egg_stock',
            'from' => now()->subDays(5)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('date,cage,size,count,freshness', $csv);
        $this->assertStringContainsString('CAGE-A,Large,333', $csv);
    }

    /**
     * Regression test for item #84 (part 1): generating a report lands on a
     * preview table first — not the printable letterhead document.
     *
     * @test
     */
    public function generating_a_report_shows_preview_before_printable_document()
    {
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');
        $this->createProductionLog($this->slotA1, 4, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'production',
            'from' => now()->subDays(2)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('Report Preview');
        $response->assertSee('View Printable Report');
        $response->assertSee('Total Eggs');          // summary pills present in preview
        $response->assertSee('ISA Brown');           // data rows present in preview
        $response->assertDontSee('Noted by:');       // signature block is full-doc only
        $response->assertDontSee('LayRate Poultry Farm'); // letterhead is full-doc only
    }

    /**
     * Regression test for item #84 (part 2): ?full=1 renders the printable
     * letterhead document.
     *
     * @test
     */
    public function full_parameter_shows_printable_document()
    {
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');
        $this->createProductionLog($this->slotA1, 4, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'production',
            'from' => now()->subDays(2)->toDateString(),
            'to'   => now()->toDateString(),
            'cage' => 'all',
            'full' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('LayRate Poultry Farm'); // letterhead
        $response->assertSee('Noted by:');            // signature block
        $response->assertSee('Back to Preview');
        $response->assertDontSee('Report Preview');
    }

    /** @test */
    public function empty_date_range_shows_no_data_message()
    {
        $response = $this->actingAs($this->user)->get(route('reports', [
            'type' => 'egg_stock',
            'from' => '2020-01-01',
            'to'   => '2020-01-07',
            'cage' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('No data found for the selected filters.');
    }

    /** @test */
    public function every_report_type_returns_200_with_data_present()
    {
        $this->createHen($this->slotA1, 'ISA Brown', true, 'A-HEN1');
        $this->createProductionLog($this->slotA1, 4, now()->subDay()->toDateString());

        $batch = FeedBatch::create([
            'crude_protein' => 17.5,
            'total_quantity_kg' => 100,
            'date_received' => now()->subDays(4)->toDateString(),
        ]);
        $feedLog = new FeedConsumptionLog();
        $feedLog->cage_id = $this->cageA->id;
        $feedLog->feed_batch_id = $batch->id;
        $feedLog->log_date = now()->subDay()->toDateString();
        $feedLog->feed_consumed_kg = 12.5;
        $feedLog->recorded_by = $this->user->id;
        $feedLog->save();

        EnvironmentalLog::create([
            'cage_id' => $this->cageA->id,
            'recorded_at' => now()->subDay(),
            'temperature_c' => 28.0,
            'humidity_pct' => 65.0,
        ]);

        $mortality = new MortalityLog();
        $mortality->cage_id = $this->cageA->id;
        $mortality->log_date = now()->subDay()->toDateString();
        $mortality->count = 1;
        $mortality->reason = 'Disease';
        $mortality->recorded_by = $this->user->id;
        $mortality->save();

        EggStockBatch::create(['egg_size' => 'large', 'count' => 30, 'harvested_date' => now()->subDay()->toDateString(), 'cage_id' => $this->cageA->id]);

        foreach (['production', 'feed', 'environment', 'mortality', 'egg_stock'] as $type) {
            $response = $this->actingAs($this->user)->get(route('reports', [
                'type' => $type,
                'from' => now()->subDays(5)->toDateString(),
                'to'   => now()->toDateString(),
                'cage' => 'all',
            ]));

            $response->assertOk();
            $response->assertDontSee('No data found for the selected filters.');
        }
    }
}
