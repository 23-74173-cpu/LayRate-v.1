<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Forecast;
use App\Models\ProductionLog;
use App\Models\User;
use App\Services\ReportingDateService;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * A backtest is a fixed claim about a day that has already happened, so it has
 * to outlive the reporting date that produced it.
 *
 * The calendar used to read only rows whose forecast_date equalled the current
 * reporting date. That is right for a forward forecast — tomorrow's really is a
 * different thing — but it meant a scored month disappeared from the calendar
 * overnight while every row sat untouched in the table, which read as "the
 * backtest never saved". "Clear forecast" compounded it by hard-deleting those
 * same rows.
 */
class ForecastBacktestRetentionTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
    }

    private function completedDate(int $daysAgo = 2): string
    {
        return ReportingDateService::now()->copy()->subDays($daysAgo)->toDateString();
    }

    /** @return array{0:int,1:int} */
    private function monthYearFor(string $date): array
    {
        return [(int) substr($date, 5, 2), (int) substr($date, 0, 4)];
    }

    private function seedCageWithProduction(string $code, string $date, int $eggs = 100): Cage
    {
        $cage = Cage::create([
            'cage_code' => $code,
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 1,
            'max_chickens_per_slot' => 50,
            'total_capacity' => 50,
            'is_active' => 1,
        ]);

        $slot = CageSlot::create([
            'cage_id' => $cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 10,
        ]);

        ProductionLog::create([
            'cage_slot_id' => $slot->id,
            'log_date' => $date,
            'egg_count' => $eggs,
            'hen_count' => 10,
        ]);

        return $cage;
    }

    /**
     * The core regression: a backtest generated on an *earlier* reporting date
     * must still be on the calendar. Before the fix this rendered nothing,
     * because the read path demanded forecast_date === today.
     */
    public function test_backtest_from_an_earlier_run_still_renders_on_the_calendar(): void
    {
        $target = $this->completedDate(10);
        $cage = $this->seedCageWithProduction('CAGE-OLD-BT', $target, 512);

        Forecast::create([
            'cage_id' => $cage->id,
            'breed' => null,
            // Generated a week ago, not today.
            'forecast_date' => ReportingDateService::now()->copy()->subDays(7)->toDateString(),
            'target_date' => $target,
            'predicted_egg_count' => 480,
            'is_backtest' => true,
        ]);

        [$month, $year] = $this->monthYearFor($target);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => $cage->cage_code,
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $response->assertSee('backtest-forecast-badge');
        $response->assertSeeInOrder(['forecast-badge', 'BT', '480']);
    }

    /**
     * The subtle half. Backtests are historical, so ordering by target_date
     * puts them first; a single query with `limit($horizon)` would spend the
     * whole limit on them and the forward forecast would vanish. Both kinds
     * are placed in the *same* displayed month so that regression is actually
     * exercised, anchored to a fixed past month so the test does not break when
     * the reporting date happens to sit near a month boundary.
     */
    public function test_forward_forecast_still_renders_alongside_backtests(): void
    {
        $monthStart = ReportingDateService::now()->copy()->subMonths(2)->startOfMonth();
        $cage = $this->seedCageWithProduction(
            'CAGE-MIX',
            $monthStart->copy()->addDays(5)->toDateString(),
            512,
        );

        // More backtests than any small horizon, all inside the same month.
        foreach (range(0, 6) as $i) {
            Forecast::create([
                'cage_id' => $cage->id,
                'breed' => null,
                'forecast_date' => $this->completedDate(7),
                'target_date' => $monthStart->copy()->addDays($i)->toDateString(),
                // Kept under 1000 so number_format() does not insert a
                // thousands separator and break the string assertions.
                'predicted_egg_count' => 611 + $i,
                'is_backtest' => true,
            ]);
        }

        // A non-backtest row in the same month, generated as of today.
        Forecast::create([
            'cage_id' => $cage->id,
            'breed' => null,
            'forecast_date' => ReportingDateService::reportingDateString(),
            'target_date' => $monthStart->copy()->addDays(20)->toDateString(),
            'predicted_egg_count' => 888,
            'is_backtest' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => $cage->cage_code,
            'month' => (int) $monthStart->format('n'),
            'year' => (int) $monthStart->format('Y'),
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        // The backtests are all there...
        $response->assertSee('backtest-forecast-badge');
        $response->assertSee('611');
        $response->assertSee('617');
        // ...and the forward forecast was not starved out by them.
        $response->assertSee('888');
        $response->assertSee('bg-[#D5E8D4]', false);
    }

    /**
     * "Clear forecast" means "drop today's forward predictions". It must not
     * destroy scored history — the reason the backtest was generated at all.
     */
    public function test_clear_preserves_backtests_but_removes_forward_forecasts(): void
    {
        $btTarget = $this->completedDate(10);
        $btCage = $this->seedCageWithProduction('CAGE-CLEAR-BT', $btTarget, 512);

        Forecast::create([
            'cage_id' => $btCage->id,
            'breed' => null,
            'forecast_date' => ReportingDateService::reportingDateString(),
            'target_date' => $btTarget,
            'predicted_egg_count' => 480,
            'is_backtest' => true,
        ]);

        $fwdTarget = ReportingDateService::now()->copy()->addDays(2)->toDateString();
        $fwdCage = $this->seedCageWithProduction('CAGE-CLEAR-FWD', $fwdTarget, 512);

        Forecast::create([
            'cage_id' => $fwdCage->id,
            'breed' => null,
            'forecast_date' => ReportingDateService::reportingDateString(),
            'target_date' => $fwdTarget,
            'predicted_egg_count' => 777,
            'is_backtest' => false,
        ]);

        $this->actingAs($this->admin)->post(route('forecast.clear'), [
            'scope' => 'cage',
            'cage' => $fwdCage->cage_code,
        ])->assertRedirect();

        $this->assertSame(0, Forecast::where('cage_id', $fwdCage->id)->count(), 'forward forecast should be cleared');
        $this->assertSame(1, Forecast::where('cage_id', $btCage->id)->count(), 'backtest must survive a clear');
    }

    /**
     * A superseded backtest stays out of the calendar (only the newest
     * prediction for a date is shown) but the row is still retained.
     */
    public function test_superseded_backtest_is_hidden_but_retained(): void
    {
        $target = $this->completedDate(10);
        $cage = $this->seedCageWithProduction('CAGE-SUP-BT', $target, 512);

        Forecast::create([
            'cage_id' => $cage->id,
            'breed' => null,
            'forecast_date' => $this->completedDate(7),
            'target_date' => $target,
            'predicted_egg_count' => 311,
            'is_backtest' => true,
            'superseded_at' => now(),
        ]);

        Forecast::create([
            'cage_id' => $cage->id,
            'breed' => null,
            'forecast_date' => ReportingDateService::reportingDateString(),
            'target_date' => $target,
            'predicted_egg_count' => 322,
            'is_backtest' => true,
        ]);

        [$month, $year] = $this->monthYearFor($target);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => $cage->cage_code,
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $response->assertSee('322');
        $response->assertDontSee('311');
        $this->assertSame(2, Forecast::where('cage_id', $cage->id)->count(), 'both rows retained');
    }
}
