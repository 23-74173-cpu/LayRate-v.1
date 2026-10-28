<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reports: date filters with one date, summary HDEP over hen-days, the
 * environment row cap notice, and what the report table shows.
 */
class ReportFiltersAndSummaryTest extends TestCase
{
    private User $user;
    private Cage $cage;
    private CageSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->cage = Cage::create([
            'cage_code' => 'CAGE-RPT', 'location' => 'Test', 'rows' => 1,
            'slots_per_row' => 1, 'max_chickens_per_slot' => 5, 'total_capacity' => 5, 'is_active' => 1,
        ]);
        $this->slot = CageSlot::create([
            'cage_id' => $this->cage->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 5,
        ]);
    }

    private function log(int $daysAgo, int $eggs, int $hens): void
    {
        ProductionLog::create([
            'cage_slot_id' => $this->slot->id, 'log_date' => now()->subDays($daysAgo)->toDateString(),
            'egg_count' => $eggs, 'hen_count' => $hens, 'hdep' => round($eggs / $hens * 100, 2),
        ]);
    }

    private function report(array $query)
    {
        return $this->actingAs($this->user)->get(route('reports', ['type' => 'production', 'cage' => 'CAGE-RPT'] + $query));
    }

    public function test_from_date_alone_reports_up_to_today(): void
    {
        $this->log(10, 3, 3);
        $this->log(2, 2, 3);

        $this->report(['from' => now()->subDays(5)->toDateString()])
            ->assertOk()
            ->assertViewHas('to', now()->toDateString())
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
    }

    public function test_to_date_alone_reports_from_the_first_record(): void
    {
        $this->log(10, 3, 3);
        $this->log(2, 2, 3);

        $this->report(['to' => now()->subDays(5)->toDateString()])
            ->assertOk()
            ->assertViewHas('from', now()->subDays(10)->toDateString())
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
    }

    public function test_reversed_dates_are_swapped(): void
    {
        $this->log(10, 3, 3);
        $this->log(2, 2, 3);

        $this->report(['from' => now()->subDays(1)->toDateString(), 'to' => now()->subDays(12)->toDateString()])
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 2);
    }

    public function test_all_time_avg_hdep_divides_eggs_by_hen_days(): void
    {
        // 3 of 3 hens, then 1 of 5 hens: 4 eggs over 8 hen-days is 50.0%
        // (the plain average of the two daily rates would say 60.0%).
        $this->log(3, 3, 3);
        $this->log(2, 1, 5);

        $summary = $this->summary(null, null);

        $this->assertSame('50.0%', $summary['Avg HDEP']);
        $this->assertSame('4 hens', $summary['Avg Hens per Day']);
        $this->assertSame('2 days', $summary['Days Covered']);
    }

    public function test_avg_hdep_for_a_range_ending_today_uses_that_range(): void
    {
        $this->log(1, 4, 5);
        $this->log(0, 5, 5);

        $this->assertSame('90.0%', $this->summary(now()->subDays(3)->toDateString(), now()->toDateString())['Avg HDEP']);
    }

    public function test_environment_summary_says_when_readings_are_cut(): void
    {
        $rows = [];
        for ($i = 0; $i < 201; $i++) {
            $rows[] = ['cage_id' => $this->cage->id, 'recorded_at' => now()->subMinutes($i), 'temperature_c' => 27, 'humidity_pct' => 60, 'is_override' => 0, 'is_demo' => 0, 'created_at' => now()];
        }
        DB::table('environmental_logs')->insert($rows);

        $this->actingAs($this->user)->get(route('reports', ['type' => 'environment', 'cage' => 'CAGE-RPT']))
            ->assertOk()
            ->assertSee('Latest 200 of 201', false);
    }

    public function test_empty_columns_are_left_out_of_the_table(): void
    {
        $this->log(2, 2, 3);

        $this->report([])->assertOk()->assertSee('HDEP')->assertDontSee('FARM HENS');
    }

    public function test_letterhead_names_the_egg_stock_report_without_an_underscore(): void
    {
        $this->actingAs($this->user)->get(route('reports', ['type' => 'egg_stock']))
            ->assertOk()
            ->assertDontSee('Egg_stock');
    }

    public function test_active_filter_count_ignores_the_default_report_type(): void
    {
        $this->actingAs($this->user)->get(route('reports', ['type' => 'production']))->assertDontSee('Active filters:');
        // Both dates count as one filter, as in filter-ui.js.
        $this->actingAs($this->user)->get(route('reports', ['type' => 'production', 'from' => now()->subDays(5)->toDateString()]))
            ->assertSee('Active filters: 1');
    }

    private function summary(?string $from, ?string $to): array
    {
        $controller = new \App\Http\Controllers\ReportController();
        $method = new \ReflectionMethod($controller, 'buildSummary');
        $method->setAccessible(true);

        return $method->invoke($controller, 'production', $from, $to, 'CAGE-RPT', 'all', Cage::orderBy('cage_code')->get());
    }
}
