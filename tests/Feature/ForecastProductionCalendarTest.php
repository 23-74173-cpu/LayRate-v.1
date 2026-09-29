<?php

namespace Tests\Feature;

use App\Forecast\ForecastRules;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\Forecast;
use App\Models\ForecastRun;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\User;
use App\Services\ForecastGenerationService;
use App\Services\ReportingDateService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * Covers the Production Calendar's "actual data" layer: the grid renders
 * recorded production alongside the forecast, the actual side is scoped by
 * farm / cage / breed, past months are browsable, and past days that hold real
 * production are selectable so a prediction can be generated for a day that has
 * already happened and then scored against it.
 */
class ForecastProductionCalendarTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
    }

    /**
     * A completed day (the reporting date itself is excluded by the query —
     * today is still in progress and never feeds the dataset).
     */
    private function completedDate(int $daysAgo = 2): string
    {
        return ReportingDateService::now()->copy()->subDays($daysAgo)->toDateString();
    }

    /** @return array{0:int,1:int} the month/year to navigate the calendar to */
    private function monthYearFor(string $date): array
    {
        return [(int) substr($date, 5, 2), (int) substr($date, 0, 4)];
    }

    /**
     * The breed scope filters on a cage's first active hen, so a cage needs one
     * for the breed filter to resolve to it.
     */
    private function seedHenForCage(Cage $cage, string $breed): void
    {
        $slot = $cage->cageSlots()->first();

        $hen = new Hen([
            'tag_code' => $cage->cage_code.'-H1',
            'breed' => $breed,
            'flock_age_weeks' => 30,
            'date_acquired' => now()->subMonths(8),
            'placement_date' => now()->subMonths(8),
            'age_at_placement_weeks' => 0,
            'is_active' => 1,
        ]);
        $hen->cage_slot_id = $slot->id;
        $hen->save();
    }

    /**
     * A cage carrying exactly one production log on one past date, so the
     * expected badge value is deterministic and cannot be confused with the
     * seeded data behind it.
     */
    private function seedSingleLogCage(string $cageCode, string $logDate, int $eggCount, int $henCount, string $breed = 'ISA Brown'): Cage
    {
        $cage = Cage::create([
            'cage_code' => $cageCode,
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
            'current_occupancy' => 50,
        ]);

        ProductionLog::create([
            'cage_slot_id' => $slot->id,
            'log_date' => $logDate,
            'hen_count' => $henCount,
            'egg_count' => $eggCount,
            'hdep' => 90.0,
            'logged_via' => 'unknown',
        ]);

        return $cage;
    }

    public function test_service_returns_actual_production_keyed_by_date(): void
    {
        $date = $this->completedDate();
        // 777 eggs from 1000 hens is a 77.7% lay rate.
        $this->seedSingleLogCage('CAGE-ACTUAL-A', $date, 777, 1000);

        $rows = app(ForecastGenerationService::class)
            ->productionForRange('cage', $date, $date, 'CAGE-ACTUAL-A');

        $this->assertCount(1, $rows);
        $this->assertSame(777, $rows->get($date)['egg_count']);
        $this->assertSame(1000, $rows->get($date)['hen_count']);
        $this->assertEqualsWithDelta(77.7, $rows->get($date)['hdep'], 0.01);
    }

    public function test_service_excludes_the_in_progress_reporting_date(): void
    {
        $this->seedSingleLogCage('CAGE-ACTUAL-TODAY', ReportingDateService::reportingDateString(), 555, 100);

        $rows = app(ForecastGenerationService::class)
            ->productionForRange('cage', '2000-01-01', '2099-12-31', 'CAGE-ACTUAL-TODAY');

        $this->assertCount(0, $rows);
    }

    public function test_calendar_renders_actual_production_badge_for_cage_scope(): void
    {
        $date = $this->completedDate();
        $this->seedSingleLogCage('CAGE-ACTUAL-B', $date, 1234, 200);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => 'CAGE-ACTUAL-B',
            'month' => $month,
            'year' => $year,
        ]));

        $response->assertOk();
        $response->assertSee('production-badge');
        // Actual egg count for the seeded day.
        $response->assertSee('1,234');
    }

    public function test_calendar_cage_scope_excludes_other_cages_production(): void
    {
        $date = $this->completedDate();
        $this->seedSingleLogCage('CAGE-ACTUAL-C', $date, 4242, 100);
        $this->seedSingleLogCage('CAGE-ACTUAL-D', $date, 999, 100);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => 'CAGE-ACTUAL-D',
            'month' => $month,
            'year' => $year,
        ]));

        $response->assertOk();
        $response->assertSee('999');
        $response->assertDontSee('4,242');
    }

    public function test_calendar_farm_scope_aggregates_all_cages(): void
    {
        $date = $this->completedDate();
        $this->seedSingleLogCage('CAGE-ACTUAL-E', $date, 111, 100);
        $this->seedSingleLogCage('CAGE-ACTUAL-F', $date, 222, 100);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'farm',
            'month' => $month,
            'year' => $year,
        ]));

        $response->assertOk();
        // Farm scope sums both cages onto the same date.
        $response->assertSee('333');
    }

    public function test_calendar_allows_navigating_to_a_past_month(): void
    {
        // A month comfortably in the past — the old calendar refused to render
        // anything before the current month.
        $past = ReportingDateService::now()->copy()->subMonths(3);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'farm',
            'month' => $past->month,
            'year' => $past->year,
        ]));

        $response->assertOk();
        $response->assertSee('Production Calendar');
        // The past month must be selectable in the month dropdown.
        $response->assertSee('value="'.$past->month.'" selected', false);
        // The "Past months are not available" guard is gone.
        $response->assertDontSee('Past months are not available');
    }

    public function test_calendar_year_filter_offers_the_earliest_production_year(): void
    {
        // Seeded records are demo data, so nothing real exists until we add one.
        $old = ReportingDateService::now()->copy()->subYears(2)->startOfYear()->toDateString();
        $this->seedSingleLogCage('CAGE-ACTUAL-OLD', $old, 10, 10);

        $earliest = app(ForecastGenerationService::class)->earliestProductionDate();
        $this->assertNotNull($earliest);
        $this->assertSame($old, $earliest);

        $earliestYear = (int) substr($earliest, 0, 4);
        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'farm',
            'month' => 1,
            'year' => $earliestYear,
        ]));

        $response->assertOk();
        // The year dropdown must offer the earliest year holding real data.
        $response->assertSee('<option value="'.$earliestYear.'"', false);
    }

    public function test_calendar_turbo_frame_request_still_renders_actual_production(): void
    {
        $date = $this->completedDate();
        $this->seedSingleLogCage('CAGE-ACTUAL-G', $date, 3131, 100);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => 'CAGE-ACTUAL-G',
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $response->assertSee('production-calendar');
        $response->assertSee('production-badge');
        $response->assertSee('3,131');
    }

    /**
     * The day number no longer carries a "BT" chip. Backtest is instead
     * signalled by a "BT" label sitting on the forecast badge, and the badge
     * itself is solid black so it reads as a third, distinct series against
     * the blue actual and the green forward forecast.
     */
    public function test_backtest_forecast_badge_is_black_and_labelled_bt(): void
    {
        $date = $this->completedDate(3);
        $cage = $this->seedSingleLogCage('CAGE-BT-STYLE', $date, 512, 100);

        // Cage-scope rows carry a cage_id and no breed, and the calendar reads
        // predictions generated as of the current reporting date.
        Forecast::create([
            'cage_id' => $cage->id,
            'breed' => null,
            'forecast_date' => ReportingDateService::reportingDateString(),
            'target_date' => $date,
            'predicted_egg_count' => 498,
            'is_backtest' => true,
        ]);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => $cage->cage_code,
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $response->assertSee('498');
        // A semantic class on the badge is what distinguishes the cell's
        // backtest treatment from the black swatch in the legend, which
        // shares the same colour.
        $response->assertSee('backtest-forecast-badge');
        $response->assertDontSee('EDE9FE', false);
        // "BT" is rendered inside the badge, immediately before the figure.
        $response->assertSeeInOrder(['forecast-badge', 'BT', '498']);
        // The removed chip must not linger on the day number.
        $response->assertDontSee('backtest-mark');
    }

    /**
     * A forward forecast keeps its green badge and carries no "BT" label, so
     * the black treatment cannot leak onto ordinary predictions.
     */
    public function test_forward_forecast_badge_is_unlabelled_and_not_black(): void
    {
        // A future day, so the calendar treats it as a forward prediction
        // rather than a backtest. Kept in the same month as the reporting
        // date so it lands inside the rendered grid.
        $date = ReportingDateService::now()->copy()->addDays(3)->toDateString();
        $cage = $this->seedSingleLogCage('CAGE-FWD-STYLE', $date, 512, 100);

        Forecast::create([
            'cage_id' => $cage->id,
            'breed' => null,
            'forecast_date' => ReportingDateService::reportingDateString(),
            'target_date' => $date,
            'predicted_egg_count' => 530,
            'is_backtest' => false,
        ]);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => $cage->cage_code,
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $response->assertSee('530');
        $response->assertSee('bg-[#D5E8D4]', false);
        // The backtest treatment must not leak onto a forward prediction.
        $response->assertDontSee('backtest-forecast-badge');
        // No "BT" label between the badge and its figure. (A bare
        // assertDontSee('BT') is not usable: it also matches the "Btn" in
        // dayDragSelectToggleBtn and .day-scope-btn.)
        $response->assertSeeInOrder(['forecast-badge', '530']);
    }

    /** The actual figure is labelled "AD" directly beside its number. */
    public function test_actual_badge_is_labelled_ad_beside_the_figure(): void
    {
        $date = $this->completedDate();
        $cage = $this->seedSingleLogCage('CAGE-AD-LABEL', $date, 777, 100);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => $cage->cage_code,
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $response->assertSee('production-badge');
        $response->assertSeeInOrder(['production-badge', 'AD', '777']);
    }

    /**
     * The scope buttons in Forecast Inputs re-request the calendar as a
     * #production-calendar turbo-frame rather than navigating, so each scope
     * has to return its own production from that same endpoint.
     */
    public function test_calendar_frame_returns_whole_farm_production_when_scope_is_farm(): void
    {
        $date = $this->completedDate();
        $this->seedSingleLogCage('CAGE-FARM-A', $date, 300, 100);
        $this->seedSingleLogCage('CAGE-FARM-B', $date, 450, 100);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'farm',
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $response->assertSee('production-calendar');
        // 300 + 450 summed farm-wide onto the single date.
        $response->assertSee('750');
    }

    public function test_calendar_frame_returns_only_the_selected_cage_when_scope_is_cage(): void
    {
        $date = $this->completedDate();
        $this->seedSingleLogCage('CAGE-FRAME-A', $date, 300, 100);
        $this->seedSingleLogCage('CAGE-FRAME-B', $date, 450, 100);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => 'CAGE-FRAME-B',
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $response->assertSee('450');
        $response->assertDontSee('300');
    }

    public function test_calendar_frame_scopes_production_to_the_selected_breed(): void
    {
        $date = $this->completedDate();

        // A hen is required for the breed filter, which keys off a cage's first
        // active hen.
        $alpha = $this->seedSingleLogCage('CAGE-BREED-A', $date, 300, 100, 'ISA Brown');
        $beta = $this->seedSingleLogCage('CAGE-BREED-B', $date, 450, 100, 'Dekalb White');

        $this->seedHenForCage($alpha, 'ISA Brown');
        $this->seedHenForCage($beta, 'Dekalb White');

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'breed',
            'breed' => 'ISA Brown',
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $response->assertSee('300');
        $response->assertDontSee('450');
    }

    /**
     * The whole point of the change: a day that has already happened must be
     * selectable for forecasting, so a prediction can be produced for it and
     * then scored against the actual recorded for that day.
     */
    public function test_past_day_with_production_is_selectable_for_forecasting(): void
    {
        $date = $this->completedDate(3);
        $cage = $this->seedSingleLogCage('CAGE-PAST', $date, 512, 100);

        [$month, $year] = $this->monthYearFor($date);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'cage',
            'cage' => $cage->cage_code,
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();

        // Clickable, so a prediction can be produced for the day and then
        // scored against the actual recorded for it. The backtest chip that
        // used to sit on the day number is gone; backtest days are now
        // identified by the "BT" label on the forecast badge itself, so there
        // is nothing to assert on the day number here.
        $this->assertDaySelectable($response, $date, true);
        $response->assertDontSee('backtest-mark');
    }

    /**
     * A day before any production exists cannot be backtested — there is no
     * actual to compare against — so it stays unselectable even though it is in
     * the past.
     */
    public function test_past_day_before_any_production_is_not_selectable(): void
    {
        $date = $this->completedDate(10);
        $this->seedSingleLogCage('CAGE-FIRST', $date, 400, 100);

        $earliest = $this->forecastService()->earliestProductionDate();
        $this->assertSame($date, $earliest);

        $before = Carbon::parse($earliest)->subDay()->toDateString();
        [$month, $year] = $this->monthYearFor($before);

        $response = $this->actingAs($this->admin)->get(route('forecast', [
            'scope' => 'farm',
            'month' => $month,
            'year' => $year,
        ]), ['Turbo-Frame' => 'production-calendar']);

        $response->assertOk();
        $this->assertDaySelectable($response, $before, false);
    }

    /**
     * A backtest must be accepted by the controller, not rejected with the old
     * "must be at least tomorrow" guard. Sufficiency is measured against the
     * production preceding the target, mirroring the training cut.
     */
    public function test_generate_accepts_a_past_start_date_as_a_backtest(): void
    {
        $target = $this->completedDate(2);

        // 90 days of production, all strictly before the target, plus
        // environmental rows to satisfy the join the sufficiency query requires.
        $this->seedSufficientHistory(Carbon::parse($target)->subDays(91), 91);

        $this->actingAs($this->admin)->post(route('forecast.generate'), [
            'scope' => 'cage',
            'cage' => 'CAGE-HIST',
            'horizon' => 1,
            'start_date' => $target,
        ]);

        $this->assertNull(session('error'));

        $run = ForecastRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame($target, $run->start_date->toDateString());
        $this->assertTrue(ForecastRules::isBacktest($target));
    }

    /** A date with no production behind it is still rejected. */
    public function test_generate_rejects_a_date_before_any_production(): void
    {
        $target = $this->completedDate(2);
        $this->seedSufficientHistory(Carbon::parse($target)->subDays(91), 91);

        // One day before the first recorded production: unforecastable, because
        // there is no actual to score a prediction against.
        $tooEarly = Carbon::parse($target)->subDays(92)->toDateString();

        $this->actingAs($this->admin)
            ->post(route('forecast.generate'), [
                'scope' => 'cage',
                'cage' => 'CAGE-HIST',
                'horizon' => 1,
                'start_date' => $tooEarly,
            ])
            ->assertSessionHas('error');
    }

    /**
     * A contiguous run of real (non-demo) production plus matching
     * environmental rows, which the sufficiency query joins on.
     */
    private function seedSufficientHistory(Carbon $from, int $days): Cage
    {
        $cage = Cage::create([
            'cage_code' => 'CAGE-HIST',
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
            'current_occupancy' => 50,
        ]);

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();

            ProductionLog::create([
                'cage_slot_id' => $slot->id,
                'log_date' => $day,
                'hen_count' => 100,
                'egg_count' => 90,
                'hdep' => 90.0,
                'logged_via' => 'unknown',
            ]);

            EnvironmentalLog::create([
                'cage_id' => $cage->id,
                'recorded_at' => $day.' 08:00:00',
                'temperature_c' => 27.0,
                'humidity_pct' => 60.0,
            ]);
        }

        return $cage;
    }

    /**
     * Asserts a day cell's selectability. The attributes are rendered on
     * separate lines, so a whitespace-tolerant pattern is required.
     */
    private function assertDaySelectable($response, string $date, bool $selectable): void
    {
        $this->assertMatchesRegularExpression(
            '/data-date="'.preg_quote($date, '/').'"\s+data-selectable="'.($selectable ? 'true' : 'false').'"/',
            $response->getContent()
        );
    }

    private function forecastService(): ForecastGenerationService
    {
        return app(ForecastGenerationService::class);
    }
}
