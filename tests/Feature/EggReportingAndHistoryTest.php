<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EggSizeLog;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EggReportingAndHistoryTest extends TestCase
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

        // Active hens with distinct breeds per cage.
        // cage_slot_id is guarded on Hen, so assign it after creation to match production patterns.
        foreach (range(1, 4) as $i) {
            $hen = Hen::create([
                'tag_code' => "A-HEN{$i}",
                'breed' => 'ISA Brown',
                'flock_age_weeks' => 28,
                'date_acquired' => now()->subMonths(6)->toDateString(),
                'placement_date' => now()->subMonths(6)->toDateString(),
                'age_at_placement_weeks' => 0,
                'is_active' => 1,
            ]);
            $hen->cage_slot_id = $this->slotA1->id;
            $hen->save();
        }

        foreach (range(1, 4) as $i) {
            $hen = Hen::create([
                'tag_code' => "B-HEN{$i}",
                'breed' => 'Dekalb White',
                'flock_age_weeks' => 34,
                'date_acquired' => now()->subMonths(8)->toDateString(),
                'placement_date' => now()->subMonths(8)->toDateString(),
                'age_at_placement_weeks' => 0,
                'is_active' => 1,
            ]);
            $hen->cage_slot_id = $this->slotB1->id;
            $hen->save();
        }
    }

    private function createLog(CageSlot $slot, int $eggs, string $date, ?string $notes, ?string $loggedVia = null): ProductionLog
    {
        $log = new ProductionLog();
        $log->cage_slot_id = $slot->id;
        $log->log_date = $date;
        $log->egg_count = $eggs;
        $log->hen_count = 4;
        $log->hdep = round(($eggs / 4) * 100, 2);
        $log->notes = $notes;
        $log->logged_via = $loggedVia ?? 'unknown';
        $log->recorded_by = $this->user->id;
        $log->save();

        return $log;
    }

    /** @test */
    public function logged_via_backfill_classifies_notes_correctly()
    {
        // Create records with known notes *before* applying backfill by leaving logged_via null.
        $sensor1 = $this->createLog($this->slotA1, 4, now()->toDateString(), 'IR sensor synced');
        $sensor2 = $this->createLog($this->slotA1, 4, now()->subDay()->toDateString(), 'Sensor reading confirmed');
        $manual1 = $this->createLog($this->slotA1, 4, now()->subDays(2)->toDateString(), 'Manual entry');
        $manual2 = $this->createLog($this->slotA1, 4, now()->subDays(3)->toDateString(), 'Manual check');
        $unknown1 = $this->createLog($this->slotA1, 4, now()->subDays(4)->toDateString(), null);
        $unknown2 = $this->createLog($this->slotA1, 4, now()->subDays(5)->toDateString(), 'Routine count');

        // Reset logged_via to simulate pre-migration state.
        ProductionLog::query()->update(['logged_via' => 'unknown']);

        // Re-run the backfill logic from the migration.
        ProductionLog::whereNotNull('notes')
            ->where(function ($q) {
                $q->where('notes', 'like', '%sensor%')
                  ->orWhere('notes', 'like', '%IR%');
            })
            ->update(['logged_via' => 'sensor']);

        ProductionLog::whereNotNull('notes')
            ->where(function ($q) {
                $q->where('notes', 'like', '%Manual%')
                  ->orWhere('notes', 'like', '%manual%');
            })
            ->update(['logged_via' => 'manual']);

        $this->assertEquals('sensor', $sensor1->fresh()->logged_via);
        $this->assertEquals('sensor', $sensor2->fresh()->logged_via);
        $this->assertEquals('manual', $manual1->fresh()->logged_via);
        $this->assertEquals('manual', $manual2->fresh()->logged_via);
        $this->assertEquals('unknown', $unknown1->fresh()->logged_via);
        $this->assertEquals('unknown', $unknown2->fresh()->logged_via);
    }

    /** @test */
    public function recent_logs_filter_by_cage()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $this->actingAs($this->user);

        // The table rows are rendered by the lazy-loaded egg-logs-list turbo frame; test that endpoint directly.
        $response = $this->get(route('eggs.logging.logs', ['cage_id' => $this->cageA->id]));
        $response->assertOk();
        $response->assertSee('CAGE-A');
        $response->assertDontSee('CAGE-B');
    }

    /** @test */
    public function recent_logs_filter_by_slot()
    {
        $slotA2 = CageSlot::create([
            'cage_id' => $this->cageA->id,
            'slot_number' => 2,
            'row_number' => 1,
            'column_number' => 2,
            'current_occupancy' => 4,
        ]);

        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($slotA2, 7, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $this->actingAs($this->user);

        $response = $this->get(route('eggs.logging.logs', ['cage_slot_id' => $this->slotA1->id]));
        $response->assertOk();
        $response->assertSee('1-1');
        $response->assertDontSee('1-2');
    }

    /** @test */
    public function recent_logs_filter_by_breed()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $this->actingAs($this->user);

        $response = $this->get(route('eggs.logging.logs', ['breed' => 'ISA Brown']));
        $response->assertOk();
        $response->assertSee('CAGE-A');
        $response->assertDontSee('CAGE-B');
    }

    /** @test */
    public function recent_logs_filter_by_logged_via()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'IR sensor synced', 'sensor');
        $this->createLog($this->slotA1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotA1, 2, now()->subDays(2)->toDateString(), 'Routine count', 'unknown');

        $this->actingAs($this->user);

        $response = $this->get(route('eggs.logging.logs', ['logged_via' => ['sensor']]));
        $response->assertOk();
        $response->assertSee('Sensor');
        $response->assertDontSee('Manual');

        $response = $this->get(route('eggs.logging.logs', ['logged_via' => ['manual', 'unknown']]));
        $response->assertOk();
        $response->assertSee('Manual');
        $response->assertDontSee('Sensor');
    }

    /** @test */
    public function recent_logs_combined_filters_narrow_results()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'IR sensor synced', 'sensor');
        $this->createLog($this->slotA1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 4, now()->subDay()->toDateString(), 'IR sensor synced', 'sensor');

        $this->actingAs($this->user);

        $response = $this->get(route('eggs.logging.logs', [
            'cage_id' => $this->cageA->id,
            'breed' => 'ISA Brown',
            'logged_via' => ['sensor'],
        ]));
        $response->assertOk();
        $response->assertSee('CAGE-A');
        $response->assertDontSee('CAGE-B');
        // Only one data row (the sensor log for CAGE-A ISA Brown) should be present.
        $this->assertEquals(1, substr_count($response->getContent(), '<tr class="border-b hover:bg-black'));
    }

    /** @test */
    public function dashboard_lifetime_eggs_kpi_matches_db_sum()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $expected = ProductionLog::sum('egg_count');
        $this->assertEquals(8, $expected);

        $this->actingAs($this->user);

        // The dashboard renders KPI cards lazily via the dashboard.stats turbo-frame.
        $response = $this->get(route('dashboard.stats'));
        $response->assertOk();
        $response->assertSee('Lifetime Eggs');
        $response->assertSee(number_format($expected));
    }

    /** @test */
    public function egg_production_history_page_shows_correct_lifetime_total()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $expected = ProductionLog::sum('egg_count');

        $this->actingAs($this->user);

        $response = $this->get(route('egg-production-history'));
        $response->assertOk();
        $response->assertSee('Lifetime Total');
        $response->assertSee(number_format($expected));
    }

    /** @test */
    public function egg_production_history_timeline_aggregates_match_db()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotA1, 4, now()->subDay()->toDateString(), 'Manual entry', 'manual');

        $expectedDayTotal = ProductionLog::whereDate('log_date', now()->toDateString())->sum('egg_count');
        $expectedTotal = ProductionLog::sum('egg_count');

        $this->actingAs($this->user);

        $response = $this->get(route('egg-production-history', ['group_by' => 'day']));
        $response->assertOk();
        $response->assertSee(number_format($expectedDayTotal));
        $response->assertSee(number_format($expectedTotal));

        // Week grouping should still sum to the same lifetime total across rows.
        $weekResponse = $this->get(route('egg-production-history', ['group_by' => 'week']));
        $weekResponse->assertOk();
        $weekResponse->assertSee(number_format($expectedTotal));

        $monthResponse = $this->get(route('egg-production-history', ['group_by' => 'month']));
        $monthResponse->assertOk();
        $monthResponse->assertSee(number_format($expectedTotal));
    }

    /** @test */
    public function egg_production_history_cage_breakdown_matches_db()
    {
        $this->createLog($this->slotA1, 5, now()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotA1, 4, now()->subDay()->toDateString(), 'Manual entry', 'manual');
        $this->createLog($this->slotB1, 3, now()->toDateString(), 'Manual entry', 'manual');

        $expectedA = $this->cageA->productionLogs->sum('egg_count');
        $expectedB = $this->cageB->productionLogs->sum('egg_count');

        $this->actingAs($this->user);

        $response = $this->get(route('egg-production-history'));
        $response->assertOk();
        $response->assertSee('CAGE-A');
        $response->assertSee('CAGE-B');
        $response->assertSee(number_format($expectedA));
        $response->assertSee(number_format($expectedB));
    }

    /** @test */
    public function egg_production_history_size_breakdown_uses_egg_size_logs()
    {
        $log = $this->createLog($this->slotA1, 6, now()->toDateString(), 'Manual entry', 'manual');
        $log->eggSizeLogs()->createMany([
            ['egg_size' => 'small', 'count' => 1],
            ['egg_size' => 'medium', 'count' => 2],
            ['egg_size' => 'large', 'count' => 2],
            ['egg_size' => 'jumbo', 'count' => 1],
        ]);

        $expectedSizeTotal = EggSizeLog::sum('count');
        $this->assertEquals(6, $expectedSizeTotal);

        $this->actingAs($this->user);

        $response = $this->get(route('egg-production-history'));
        $response->assertOk();
        // The view renders the raw lowercase size value and styles it with CSS capitalize.
        $response->assertSee('small');
        $response->assertSee('medium');
        $response->assertSee('large');
        $response->assertSee('jumbo');
        $response->assertSee(number_format($expectedSizeTotal));
    }

    /** @test */
    public function cage_overview_shows_hen_count_and_empty_indicator()
    {
        $this->actingAs($this->user);

        $cageC = Cage::create([
            'cage_code' => 'CAGE-C-EMPTY',
            'location' => 'West',
            'rows' => 1,
            'slots_per_row' => 1,
            'max_chickens_per_slot' => 4,
            'total_capacity' => 4,
            'is_active' => 1,
        ]);
        CageSlot::create([
            'cage_id' => $cageC->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 0,
        ]);

        $response = $this->get(route('eggs.logging'));
        $response->assertOk();

        $response->assertSee('4 hens');
        $response->assertSee('Cage Empty');
    }

    /** @test */
    public function production_history_page_renders_calendar_frame()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('eggs.production-history'));
        $response->assertOk();
        $response->assertSee('Production History');
        $response->assertSee('dashboard-calendar');
    }

    /** @test */
    public function manual_store_sets_logged_via_to_manual_by_default()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('eggs.logging.store'), [
            'cage_slot_id' => $this->slotA1->id,
            'log_date' => now()->toDateString(),
            'egg_count' => 5,
            'hen_count' => 4,
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, ProductionLog::count());
        $this->assertEquals('manual', ProductionLog::first()->logged_via);
    }
}
