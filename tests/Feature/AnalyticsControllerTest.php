<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
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

        $hen = Hen::create([
            'tag_code' => 'A-HEN1',
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

    private function createLog(CageSlot $slot, float $hdep, string $date): ProductionLog
    {
        $log = new ProductionLog();
        $log->cage_slot_id = $slot->id;
        $log->log_date = $date;
        $log->egg_count = (int) round(($hdep / 100) * 4);
        $log->hen_count = 4;
        $log->hdep = $hdep;
        $log->logged_via = 'manual';
        $log->recorded_by = $this->user->id;
        $log->save();

        return $log;
    }

    /** @test */
    public function analytics_page_redirects_to_dashboard_for_authenticated_user()
    {
        $response = $this->actingAs($this->user)->get(route('analytics'));

        $response->assertRedirect(route('dashboard', ['days' => 7]));
    }

    /** @test */
    public function analytics_page_redirects_performance_3months_to_dashboard_with_90_days()
    {
        $response = $this->actingAs($this->user)->get(route('analytics', ['cage' => 'performance', 'period' => '3months']));

        $response->assertRedirect(route('dashboard', ['days' => 90]));
    }

    /** @test */
    public function analytics_page_redirects_cage_specific_week_to_dashboard_with_cage_param()
    {
        $response = $this->actingAs($this->user)->get(route('analytics', ['cage' => 'CAGE-A', 'period' => 'week']));

        $response->assertRedirect(route('dashboard', ['days' => 7, 'cage' => 'CAGE-A']));
    }

    /** @test */
    public function analytics_page_redirects_month_and_full_periods_with_correct_day_mapping()
    {
        $month = $this->actingAs($this->user)->get(route('analytics', ['cage' => 'CAGE-A', 'period' => 'month']));
        $month->assertRedirect(route('dashboard', ['days' => 30, 'cage' => 'CAGE-A']));

        $full = $this->actingAs($this->user)->get(route('analytics', ['cage' => 'CAGE-A', 'period' => 'full']));
        $full->assertRedirect(route('dashboard', ['days' => 0, 'cage' => 'CAGE-A']));

        $allMonth = $this->actingAs($this->user)->get(route('analytics', ['cage' => 'all', 'period' => 'month']));
        // 'all' is treated like no cage filter — redirect drops the cage param
        $allMonth->assertRedirect(route('dashboard', ['days' => 30]));
    }

    /** @test */
    public function analytics_route_still_generates_valid_bookmark_url()
    {
        $url = route('analytics', ['cage' => 'CAGE-A', 'period' => 'week']);

        $this->assertStringContainsString('/analytics', $url);
        $this->assertStringContainsString('cage=CAGE-A', $url);
        $this->assertStringContainsString('period=week', $url);

        // Hitting that bookmark now redirects to the consolidated Dashboard.
        $response = $this->actingAs($this->user)->get($url);
        $response->assertRedirect(route('dashboard', ['days' => 7, 'cage' => 'CAGE-A']));
    }

    /** @test */
    public function guest_is_redirected_to_login()
    {
        $response = $this->get(route('analytics'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function analytics_redirects_to_dashboard_when_no_cages_exist()
    {
        Cage::query()->delete();

        $response = $this->actingAs($this->user)->get(route('analytics'));

        $response->assertRedirect(route('dashboard', ['days' => 7]));
    }

    /** @test */
    public function charts_partial_returns_422_when_no_cages_exist()
    {
        Cage::query()->delete();

        $response = $this->actingAs($this->user)->get(route('analytics.charts'));

        $response->assertStatus(422);
    }

    /** @test */
    public function charts_partial_renders_correct_summary_stats()
    {
        $this->createLog($this->slotA1, 75.0, now()->subDays(2)->toDateString());
        $this->createLog($this->slotA1, 100.0, now()->subDay()->toDateString());

        // Summary stats are now served via the JSON data endpoint (KPI cards live on the
        // main Analytics page, not in the lazy charts frame). Verify the JSON payload.
        $dataResponse = $this->actingAs($this->user)
            ->get(route('analytics.data', ['cage' => 'CAGE-A', 'period' => 'week']));

        $dataResponse->assertOk();
        $dataResponse->assertJsonPath('kpi.avgHdep', 87.5);
        $dataResponse->assertJsonPath('kpi.bestDay', 100);
        $dataResponse->assertJsonPath('kpi.worstDay', 75);

        // Charts frame itself should still render the canvas shells.
        $chartsResponse = $this->actingAs($this->user)
            ->get(route('analytics.charts', ['cage' => 'CAGE-A', 'period' => 'week']));

        $chartsResponse->assertOk();
        $chartsResponse->assertSee('hdepChart');
        $chartsResponse->assertSee('eggsChart');
    }

    /** @test */
    public function cage_filter_switches_the_reported_cage()
    {
        $response = $this->actingAs($this->user)
            ->get(route('analytics.charts', ['cage' => 'CAGE-B', 'period' => 'week']));

        $response->assertOk();
        $response->assertSee('CAGE-B');
    }

    /** @test */
    public function performance_tab_uses_dashboard_cage_performance_design()
    {
        $this->createLog($this->slotA1, 100.0, now()->subDay()->toDateString());
        $this->createLog($this->slotB1, 50.0, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)
            ->get(route('analytics.charts', ['cage' => 'performance', 'period' => 'week']));

        $response->assertOk();
        $response->assertSee('Cage Performance Overview');
        $response->assertSee('HDEP by Cage');
        $response->assertSee('Eggs Distribution by Cage');
        $response->assertSee('CAGE-A');
        $response->assertSee('CAGE-B');
    }

    /** @test */
    public function full_period_returns_all_logs_since_day_one()
    {
        $this->createLog($this->slotA1, 66.0, now()->subDays(40)->toDateString());
        $this->createLog($this->slotA1, 88.0, now()->subDays(2)->toDateString());

        $response = $this->actingAs($this->user)
            ->get(route('analytics.data', ['cage' => 'CAGE-A', 'period' => 'full']));

        $response->assertOk();
        $response->assertJsonCount(2, 'charts.logs');
        $response->assertJsonFragment(['hdep' => 66.0]);
        $response->assertJsonFragment(['hdep' => 88.0]);

        // The same cage scoped to a week must not include the 40-day-old log.
        $week = $this->actingAs($this->user)
            ->get(route('analytics.data', ['cage' => 'CAGE-A', 'period' => 'week']));
        $week->assertOk();
        $week->assertJsonCount(1, 'charts.logs');

        // The charts frame renders the all-time title for the full period.
        $charts = $this->actingAs($this->user)
            ->get(route('analytics.charts', ['cage' => 'CAGE-A', 'period' => 'full']));
        $charts->assertOk();
        $charts->assertSee('ALL-TIME');
        $charts->assertSee('chart-title-period');
    }

    /**
     * Regression test for item #79: chart instances must be cached in a
     * namespaced store, never as window.<canvasId>. Bare window.hdepChart
     * collides with the browser's automatic id→global binding for
     * <canvas id="hdepChart"> (the global is the DOM node, which has no
     * .destroy()), which crashed chart init on every load, forever.
     *
     * The charts frame now delegates to the shared LayRateChart helper and the
     * parent page's renderAnalyticsCharts() rather than a bespoke
     * __analyticsCharts store. Verify the canvas IDs are present and no
     * bare window.<canvasId> globals are created.
     *
     * @test
     */
    public function charts_partial_uses_namespaced_chart_store_not_canvas_id_globals()
    {
        $this->createLog($this->slotA1, 85.0, now()->subDay()->toDateString());

        $response = $this->actingAs($this->user)
            ->get(route('analytics.charts', ['cage' => 'CAGE-A', 'period' => 'week']));

        $response->assertOk();
        $response->assertSee('hdepChart');
        $response->assertSee('eggsChart');
        $response->assertSee('feedHdepChart');
        // The shared helper is LayRateChart, and the parent page exposes
        // renderAnalyticsCharts — neither should create bare window.hdepChart globals.
        foreach (['hdepChart', 'eggsChart', 'feedHdepChart'] as $id) {
            $response->assertDontSee("window.{$id} =", false);
            $response->assertDontSee("window.{$id}.destroy", false);
        }
        $response->assertDontSee('__analyticsCharts', false);
    }
}
