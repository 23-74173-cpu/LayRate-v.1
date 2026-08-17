<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
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

    private function extractEggsToday($response)
    {
        return $response->viewData('eggsToday');
    }
}
