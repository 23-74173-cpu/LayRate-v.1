<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\FeedBatch;
use App\Models\FeedConsumptionLog;
use App\Models\Forecast;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * DashboardController::buildDashboardData() used to eager-load every
 * production_logs row ever recorded (via Cage::with('productionLogs')) just
 * to filter it down to today's/yesterday's rows in PHP — unbounded growth on
 * the most-visited page in the app. It was rewritten to compute those same
 * numbers via grouped SQL aggregates instead. These tests exist specifically
 * to prove the computed values are identical before and after that change:
 * seeded data spans today, yesterday, AND 30 days ago (well outside what the
 * old eager-load's in-memory filters would keep for "today"/"yesterday", but
 * still counted in "lifetime") across two cages, so a bug that conflated
 * cages, dates, or dropped old-but-still-lifetime-relevant rows would show
 * up here.
 */
class DashboardControllerTest extends TestCase
{
    private User $admin;
    private Cage $cageA;
    private Cage $cageB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();

        $this->cageA = Cage::create([
            'cage_code' => 'CAGE-DASH-A', 'location' => 'Test', 'rows' => 1,
            'slots_per_row' => 1, 'max_chickens_per_slot' => 10, 'total_capacity' => 10, 'is_active' => 1,
        ]);
        $this->cageB = Cage::create([
            'cage_code' => 'CAGE-DASH-B', 'location' => 'Test', 'rows' => 1,
            'slots_per_row' => 1, 'max_chickens_per_slot' => 10, 'total_capacity' => 10, 'is_active' => 1,
        ]);

        $slotA = CageSlot::create(['cage_id' => $this->cageA->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 4]);
        $slotB = CageSlot::create(['cage_id' => $this->cageB->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 2]);

        // 4 active hens in A, 2 in B — chosen so egg_count divides evenly
        // into hen_count and every HDEP below is a round percentage, so
        // assertions aren't chasing floating-point rounding.
        // cage_slot_id is deliberately not mass-assignable on Hen (see
        // MassAssignmentSafetyTest) — set it directly and save(), same
        // pattern OccupancyInvariantsTest uses.
        foreach (range(1, 4) as $i) {
            $hen = new Hen([
                'tag_code' => "DASH-A-{$i}", 'breed' => 'ISA Brown',
                'flock_age_weeks' => 30, 'date_acquired' => now()->subMonths(6), 'placement_date' => now()->subMonths(6),
                'age_at_placement_weeks' => 0, 'is_active' => 1,
            ]);
            $hen->cage_slot_id = $slotA->id;
            $hen->save();
        }
        foreach (range(1, 2) as $i) {
            $hen = new Hen([
                'tag_code' => "DASH-B-{$i}", 'breed' => 'ISA Brown',
                'flock_age_weeks' => 30, 'date_acquired' => now()->subMonths(6), 'placement_date' => now()->subMonths(6),
                'age_at_placement_weeks' => 0, 'is_active' => 1,
            ]);
            $hen->cage_slot_id = $slotB->id;
            $hen->save();
        }

        // Cage A: today=4 eggs/4 hens=100% hdep, yesterday=2/4=50%, 30 days ago=1/4=25% (lifetime-only).
        ProductionLog::create(['cage_slot_id' => $slotA->id, 'log_date' => now()->toDateString(), 'egg_count' => 4, 'hen_count' => 4, 'hdep' => 100.00, 'logged_via' => 'manual']);
        ProductionLog::create(['cage_slot_id' => $slotA->id, 'log_date' => now()->subDay()->toDateString(), 'egg_count' => 2, 'hen_count' => 4, 'hdep' => 50.00, 'logged_via' => 'manual']);
        ProductionLog::create(['cage_slot_id' => $slotA->id, 'log_date' => now()->subDays(30)->toDateString(), 'egg_count' => 1, 'hen_count' => 4, 'hdep' => 25.00, 'logged_via' => 'manual']);

        // Cage B: today=1/2=50% hdep, yesterday=1/2=50%, 30 days ago=2/2=100% (lifetime-only).
        ProductionLog::create(['cage_slot_id' => $slotB->id, 'log_date' => now()->toDateString(), 'egg_count' => 1, 'hen_count' => 2, 'hdep' => 50.00, 'logged_via' => 'manual']);
        ProductionLog::create(['cage_slot_id' => $slotB->id, 'log_date' => now()->subDay()->toDateString(), 'egg_count' => 1, 'hen_count' => 2, 'hdep' => 50.00, 'logged_via' => 'manual']);
        ProductionLog::create(['cage_slot_id' => $slotB->id, 'log_date' => now()->subDays(30)->toDateString(), 'egg_count' => 2, 'hen_count' => 2, 'hdep' => 100.00, 'logged_via' => 'manual']);
    }

    public function test_unscoped_dashboard_aggregates_across_both_cages(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        // Farm-wide: today's eggs = 4 (A) + 1 (B) = 5.
        $this->assertEquals(5, $response->viewData('eggsToday'));

        // The single global period filter drives every analytics card.
        $response->assertSee('data-global-days');
        $response->assertSee('setGlobalDays');
    }

    public function test_cage_scoped_stats_matches_hand_computed_values(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.stats', ['cage' => 'CAGE-DASH-A']));
        $response->assertOk();

        $cageA = $response->viewData('cages')->firstWhere('cage_code', 'CAGE-DASH-A');
        $this->assertNotNull($cageA, 'Scoped stats view must return only the requested cage.');

        $this->assertEquals(4, $cageA->today_eggs, 'today_eggs must be exactly today\'s row (4), not sum across all dates.');
        $this->assertEquals(4, $cageA->hen_count);
        $this->assertEquals(100.0, $cageA->today_hdep, 'today_hdep = 4 eggs / 4 hens * 100.');

        $this->assertEquals(4, $response->viewData('totalHens'), 'totalHens must be scoped to cage A only (4), not both cages (6).');
        $this->assertEquals(100.0, $response->viewData('todayHdep'));
        $this->assertEquals(4, $response->viewData('eggsToday'), 'eggsToday must equal today_eggs, not include yesterday/30-days-ago rows.');

        // hdepDelta = todayHdep(100) - yesterdayHdep(50) = 50.
        $this->assertEquals(50.0, $response->viewData('hdepDelta'), 'hdepDelta must be computed from yesterday only (50% hdep), not conflate other dates.');

        // Lifetime = 4 + 2 + 1 = 7 — must INCLUDE the 30-days-ago row that
        // today/yesterday correctly exclude. This is the one assertion that
        // would fail if the SQL rewrite accidentally date-bounded the
        // lifetime sum the same way as today/yesterday.
        $this->assertEquals(7, $response->viewData('lifetimeEggs'));
    }

    public function test_cage_scoped_stats_for_cage_b_does_not_leak_cage_a_data(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.stats', ['cage' => 'CAGE-DASH-B']));
        $response->assertOk();

        $cageB = $response->viewData('cages')->firstWhere('cage_code', 'CAGE-DASH-B');
        $this->assertEquals(1, $cageB->today_eggs, 'Cage B today_eggs (1) must not include cage A\'s today_eggs (4).');
        $this->assertEquals(2, $cageB->hen_count);
        $this->assertEquals(50.0, $cageB->today_hdep);

        $this->assertEquals(1, $response->viewData('eggsToday'));
        // Lifetime for B alone = 1 + 1 + 2 = 4, not 4+2+1 (A) + 1+1+2 (B) = 11.
        $this->assertEquals(4, $response->viewData('lifetimeEggs'));
    }

    public function test_unscoped_lifetime_eggs_includes_all_cages_and_all_dates(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.stats'));
        $response->assertOk();

        // Every seeded row across both cages: A(4+2+1) + B(1+1+2) = 11.
        // Plus whatever DatabaseSeeder itself may have seeded — assert
        // "at least" rather than "exactly" so unrelated seed data doesn't
        // make this test brittle.
        $this->assertGreaterThanOrEqual(11, $response->viewData('lifetimeEggs'));
    }

    public function test_flock_stats_partial_renders_mortality_livability_metrics(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.stats.flock'));
        $response->assertOk();

        $this->assertEquals(6, $response->viewData('totalHens'));
        $this->assertEquals(0, $response->viewData('mortalityTodayTotal'));
        $this->assertEquals(0, $response->viewData('yesterdayMortalityTotal'));

        $response->assertSee('Mortality Today')
            ->assertSee('Livability')
            ->assertSee("Yesterday's Mortality");
    }

    public function test_cage_performance_endpoint_returns_view_with_rankings(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.cage-performance'));
        $response->assertOk();

        $cages = $response->viewData('cages');
        $cageA = $cages->firstWhere('cage_code', 'CAGE-DASH-A');
        $cageB = $cages->firstWhere('cage_code', 'CAGE-DASH-B');

        // Default view is Week (7 days) — includes today + yesterday.
        $this->assertEquals(6, $cageA->period_eggs);
        $this->assertEquals(21.4, $cageA->period_hdep);
        $this->assertEquals(2, $cageB->period_eggs);
        $this->assertEquals(14.3, $cageB->period_hdep);
    }

    public function test_cage_performance_ranks_cages_by_eggs_collected(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.cage-performance'));
        $response->assertOk();

        // Default Week: Cage A = 6 eggs, Cage B = 2 eggs, so A is #1.
        $response->assertSee('CAGE-DASH-A');
        $response->assertSee('21.4%');
        $response->assertSee('6');
    }

    public function test_cage_performance_7_day_filter_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.cage-performance', ['days' => 7]));
        $response->assertOk();

        $cages = $response->viewData('cages');
        $cageA = $cages->firstWhere('cage_code', 'CAGE-DASH-A');

        $this->assertEquals(6, $cageA->period_eggs);
        $this->assertEquals(21.4, $cageA->period_hdep);
    }

    public function test_cage_performance_renders_comparison_charts(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.cage-performance'));
        $response->assertOk();

        $response->assertSee('HDEP by Cage');
        $response->assertSee('Eggs Distribution by Cage');
        $response->assertSee('dashHdepChart');
        $response->assertSee('dashEggsChart');

        // Each cage's identity color must be passed to the chart datasets
        // so the bars/slices match the cage dots/labels used elsewhere in the UI.
        $cageA = $this->cageA->fresh();
        $cageB = $this->cageB->fresh();
        $response->assertSee($cageA->color, false);
        $response->assertSee($cageB->color, false);

        // HDEP remains a bar chart; eggs are rendered as a pie chart.
        $response->assertSee("type: 'bar'", false);
        $response->assertSee("type: 'pie'", false);
    }

    public function test_production_history_renders_line_chart_defaulting_to_7_days(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.production-history'));
        $response->assertOk();

        $response->assertSee('Production History');
        $response->assertSee('dashProductionHistoryChart');
        $response->assertSee('Total Production');

        // Default days filter is 7.
        $response->assertViewHas('days', 7);
    }

    public function test_cage_performance_90_day_filter_shows_3_months_label_and_footer(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.cage-performance', ['days' => 90]));
        $response->assertOk();
        $response->assertViewHas('days', 90);
        $response->assertSee('3 Months');
        $response->assertSee('Ranked by eggs collected over the last 90 days');
        $response->assertSee('HDEP by Cage (3 Months)');
    }

    public function test_production_history_scopes_to_selected_cage(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.production-history', ['cage' => 'CAGE-DASH-A']));
        $response->assertOk();

        $response->assertSee('CAGE-DASH-A Production');
        $response->assertSee('dashProductionHistoryChart');
    }

    public function test_production_history_30_day_filter_is_accepted(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.production-history', ['days' => 30]));
        $response->assertOk();
        $response->assertViewHas('days', 30);
    }

    public function test_production_history_invalid_days_filter_defaults_to_7(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.production-history', ['days' => 99]));
        $response->assertOk();
        $response->assertViewHas('days', 7);
    }

    public function test_production_history_full_days_includes_oldest_log(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.production-history', ['cage' => 'CAGE-DASH-A', 'days' => 0]));
        $response->assertOk();
        $response->assertViewHas('days', 0);

        // Full (day 1) must include the 30-days-ago row that a 7-day window
        // excludes. Cage A: 4 (today) + 2 (yesterday) + 1 (30d ago) = 7.
        $chartData = $response->viewData('chartData');
        $this->assertEquals(7, array_sum($chartData['datasets'][0]['data']));
        // The Week/Month/Full pills live on the dashboard page's global filter,
        // not on this frame, so only the data window is asserted here.
    }

    public function test_cage_performance_full_days_accumulates_all_time_eggs(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.cage-performance', ['cage' => 'CAGE-DASH-A', 'days' => 0]));
        $response->assertOk();
        $response->assertViewHas('days', 0);

        $cageA = $response->viewData('cages')->firstWhere('cage_code', 'CAGE-DASH-A');
        // All-time for A = 4 + 2 + 1 = 7 eggs.
        $this->assertEquals(7, $cageA->period_eggs);

        // The All-Time (day 1) ranking hint and HDEP chart header must render.
        $response->assertSee('All-Time');
    }

    public function test_production_history_compare_mode_renders_multiple_datasets(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard.production-history', ['compare' => 1]));
        $response->assertOk();

        $response->assertSee('Compare');
        $chartData = $response->viewData('chartData');
        $this->assertGreaterThan(1, count($chartData['datasets']));
        $this->assertContains('CAGE-DASH-A', collect($chartData['datasets'])->pluck('label')->toArray());
        $this->assertContains('CAGE-DASH-B', collect($chartData['datasets'])->pluck('label')->toArray());
    }

    public function test_analytics_card_frames_render_at_default_and_full_days(): void
    {
        $routes = [
            'dashboard.cage-performance',
            'dashboard.production-history',
            'dashboard.egg-collection-time',
            'dashboard.hen-age-layrate',
            'dashboard.temp-vs-hdep',
            'dashboard.hum-vs-hdep',
            'dashboard.breed-analytics',
            'dashboard.mortality-by-cause',
            'dashboard.mortality-trend',
            'dashboard.feed-vs-egg',
            'dashboard.feed-by-cage',
            'dashboard.heat-stress',
        ];

        foreach ($routes as $route) {
            $frameId = str_replace('dashboard.', 'dashboard-', $route);

            $response = $this->actingAs($this->admin)->get(route($route));
            $response->assertOk();
            $response->assertSee($frameId);

            $full = $this->actingAs($this->admin)->get(route($route, ['days' => 0]));
            $full->assertOk();
            $full->assertSee($frameId);
        }
    }

    public function test_forecast_overlay_returns_empty_gracefully_when_no_forecasts(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('dashboard.forecast-overlay', ['days' => 7]));
        $response->assertOk();
        $response->assertJsonPath('hasForecast', false);
        $response->assertJsonPath('summary', null);
        $forecast = $response->json('forecast');
        $this->assertIsArray($forecast);
        $this->assertTrue(collect($forecast)->every(fn ($v) => $v === null));
        $variance = $response->json('variance');
        $this->assertTrue(collect($variance)->every(fn ($v) => $v === null));
    }

    public function test_forecast_overlay_with_partial_coverage_computes_variance(): void
    {
        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        Forecast::create([
            'cage_id' => $this->cageA->id,
            'forecast_date' => now()->subDay()->toDateString(),
            'target_date' => $yesterday,
            'predicted_egg_count' => 10,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('dashboard.forecast-overlay', ['days' => 7, 'cage' => 'CAGE-DASH-A']));
        $response->assertOk();
        $response->assertJsonPath('hasForecast', true);
        $data = $response->json();
        $idxYesterday = array_search($yesterday, $data['dateKeys']);
        $idxToday = array_search($today, $data['dateKeys']);
        $this->assertNotFalse($idxYesterday);
        $this->assertNotFalse($idxToday);
        $this->assertEquals(10, $data['forecast'][$idxYesterday]);
        $this->assertEquals(2, $data['actual'][$idxYesterday]);
        $this->assertEquals(-80.0, $data['variance'][$yesterday]);
        $this->assertNull($data['forecast'][$idxToday]);
        $this->assertNull($data['variance'][$today]);
        $this->assertNotNull($data['summary']);
        $this->assertStringContainsString('Forecast was', $data['summary']);
    }

    public function test_forecast_overlay_whole_farm_uses_farm_scope(): void
    {
        $yesterday = now()->subDay()->toDateString();
        Forecast::create([
            'cage_id' => null,
            'breed' => null,
            'forecast_date' => now()->subDay()->toDateString(),
            'target_date' => $yesterday,
            'predicted_egg_count' => 20,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('dashboard.forecast-overlay', ['days' => 7]));
        $response->assertOk();
        $data = $response->json();
        $idx = array_search($yesterday, $data['dateKeys']);
        $this->assertEquals(20, $data['forecast'][$idx]);
        // Cage-specific call should not see farm forecast
        $cageResponse = $this->actingAs($this->admin)->getJson(route('dashboard.forecast-overlay', ['days' => 7, 'cage' => 'CAGE-DASH-A']));
        $cageData = $cageResponse->json();
        $cIdx = array_search($yesterday, $cageData['dateKeys']);
        $this->assertNull($cageData['forecast'][$cIdx]);
    }

    public function test_avg_cp_this_week_is_cage_scoped_and_date_bound(): void
    {
        $batchA = FeedBatch::create([
            'crude_protein' => 16.0,
            'total_quantity_kg' => 100,
            'date_received' => now()->subDay()->toDateString(),
        ]);
        $batchB = FeedBatch::create([
            'crude_protein' => 20.0,
            'total_quantity_kg' => 100,
            'date_received' => now()->subDay()->toDateString(),
        ]);
        // Log for cage A today with batch A (16%)
        FeedConsumptionLog::create([
            'cage_id' => $this->cageA->id,
            'feed_batch_id' => $batchA->id,
            'log_date' => now()->toDateString(),
            'log_time' => '08:00',
            'feed_consumed_kg' => 10,
            'recorded_by' => $this->admin->id,
        ]);
        // Log for cage B today with batch B (20%)
        FeedConsumptionLog::create([
            'cage_id' => $this->cageB->id,
            'feed_batch_id' => $batchB->id,
            'log_date' => now()->toDateString(),
            'log_time' => '08:00',
            'feed_consumed_kg' => 10,
            'recorded_by' => $this->admin->id,
        ]);
        // Old log outside 7-day window should be ignored
        $oldBatch = FeedBatch::create([
            'crude_protein' => 30.0,
            'total_quantity_kg' => 100,
            'date_received' => now()->subDays(20)->toDateString(),
        ]);
        FeedConsumptionLog::create([
            'cage_id' => $this->cageA->id,
            'feed_batch_id' => $oldBatch->id,
            'log_date' => now()->subDays(20)->toDateString(),
            'log_time' => '08:00',
            'feed_consumed_kg' => 10,
            'recorded_by' => $this->admin->id,
        ]);

        $unscoped = $this->actingAs($this->admin)->get(route('dashboard.stats'));
        $unscoped->assertOk();
        $this->assertEquals(18.0, $unscoped->viewData('avgCp'), 'Unscoped avg CP% should average 16 and 20 from this week (old 30 excluded)');

        $scopedA = $this->actingAs($this->admin)->get(route('dashboard.stats', ['cage' => 'CAGE-DASH-A']));
        $scopedA->assertOk();
        $this->assertEquals(16.0, $scopedA->viewData('avgCp'), 'Cage A avg CP% should only reflect its own batch (16%)');

        $scopedB = $this->actingAs($this->admin)->get(route('dashboard.stats', ['cage' => 'CAGE-DASH-B']));
        $scopedB->assertOk();
        $this->assertEquals(20.0, $scopedB->viewData('avgCp'), 'Cage B avg CP% should only reflect its own batch (20%)');
    }

    private function extractEggsToday($response)
    {
        return $response->viewData('eggsToday');
    }

    public function test_production_history_to_date_caps_range_at_to_date(): void
    {
        $from = now()->subDays(30)->toDateString();
        $to = now()->subDays(10)->toDateString();

        $response = $this->actingAs($this->admin)->get(route('dashboard.production-history', [
            'from_date' => $from, 'to_date' => $to,
        ]));
        $response->assertOk();

        $chartData = $response->viewData('chartData');
        // 21 contiguous days ending exactly on the To date.
        $this->assertCount(21, $chartData['labels']);
        $this->assertEquals(
            \Carbon\Carbon::parse($to)->format('M j'),
            end($chartData['labels'])
        );
        // Only the 30-days-ago rows fall inside [from .. to]:
        // cage A 1 egg + cage B 2 eggs = 3; today/yesterday are excluded.
        $this->assertEquals(3, array_sum($chartData['datasets'][0]['data']));
    }

    public function test_dashboard_kpi_snapshot_uses_to_date(): void
    {
        $to = now()->subDay()->toDateString();

        $response = $this->actingAs($this->admin)->get(route('dashboard.stats', ['to_date' => $to]));
        $response->assertOk();

        // Yesterday's eggs: cage A 2 + cage B 1 = 3 (not today's 5).
        $this->assertEquals(3, $response->viewData('eggsToday'));
        $this->assertNotNull($response->viewData('kpiAsOf'));
    }

    public function test_invalid_to_date_is_ignored(): void
    {
        $invalid = $this->actingAs($this->admin)->get(route('dashboard.stats', ['to_date' => 'not-a-date']));
        $invalid->assertOk();

        // Behaves exactly like no to_date at all: live reporting-date
        // snapshot with no snapshot label.
        $plain = $this->actingAs($this->admin)->get(route('dashboard.stats'));
        $plain->assertOk();
        $this->assertEquals($plain->viewData('eggsToday'), $invalid->viewData('eggsToday'));
        $this->assertNull($invalid->viewData('kpiAsOf'));
    }
}
