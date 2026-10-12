<?php
namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Forecast;
use App\Models\ForecastRun;
use App\Models\ProductionLog;
use App\Models\User;
use App\Services\ForecastGenerationService;
use App\Services\ReportingDateService;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class ForecastBaselineTest extends TestCase
{
    private User $admin;
    private Cage $cage;
    private CageSlot $slot;
    private string $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
        $this->cage = Cage::create(['cage_code' => 'BASELINE-A', 'location' => 'Test', 'rows' => 1, 'slots_per_row' => 1, 'max_chickens_per_slot' => 100, 'total_capacity' => 100, 'is_active' => true]);
        $this->slot = CageSlot::create(['cage_id' => $this->cage->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1]);
        $this->target = ReportingDateService::reportingDate()->subDay()->toDateString();
    }

    private function prediction(int $value, int $lead, bool $backtest = false): Forecast
    {
        $run = ForecastRun::create(['user_id' => $this->admin->id, 'scope' => 'cage', 'cage_id' => $this->cage->id, 'cage_code' => $this->cage->cage_code, 'horizon' => 7, 'status' => 'completed']);
        return Forecast::create(['cage_id' => $this->cage->id, 'forecast_date' => \Carbon\Carbon::parse($this->target)->subDays($lead)->toDateString(), 'target_date' => $this->target, 'predicted_egg_count' => $value, 'is_backtest' => $backtest, 'forecast_run_id' => $run->id]);
    }

    private function overlay(array $extra = [])
    {
        return $this->actingAs($this->admin)->getJson(route('dashboard.forecast-overlay', array_merge(['cage' => $this->cage->cage_code, 'from_date' => $this->target, 'to_date' => $this->target], $extra)));
    }

    public function test_saved_forecast_is_the_only_comparison_even_for_legacy_runs(): void
    {
        $first = $this->prediction(100, 7);
        $first->update(['superseded_at' => now()]);
        $latest = $this->prediction(120, 1);
        $this->prediction(999, -1, true);
        $this->prediction(888, 0);
        ProductionLog::create(['cage_slot_id' => $this->slot->id, 'log_date' => $this->target, 'egg_count' => 110, 'hen_count' => 120, 'hdep' => 91.7, 'is_demo' => false]);

        $this->overlay()->assertOk()->assertJsonPath('forecast.0', 100)->assertJsonPath('actual.0', 110)->assertJsonPath('comparisonLabel', 'Forecast')->assertJsonPath('predictionDetails.'.$this->target.'.lead_days', 7);
        $this->overlay(['forecast_version' => 'latest'])->assertOk()->assertJsonPath('forecast.0', 100);
        $this->overlay(['forecast_run_id' => $first->forecast_run_id])->assertOk()->assertJsonPath('forecast.0', 100);
        $this->overlay(['forecast_run_id' => $latest->forecast_run_id])->assertOk()->assertJsonPath('forecast.0', 100);
        $this->overlay(['forecast_run_id' => 999999])->assertOk()->assertJsonPath('forecast.0', 100);
    }

    public function test_missing_production_is_pending_but_recorded_zero_is_compared(): void
    {
        $this->prediction(100, 7);
        $this->overlay()->assertOk()->assertJsonPath('actual.0', null)->assertJsonPath('variance.'.$this->target, null)->assertJsonPath('summary', 'Awaiting completed production records for comparison.');
        ProductionLog::create(['cage_slot_id' => $this->slot->id, 'log_date' => $this->target, 'egg_count' => 0, 'hen_count' => 120, 'hdep' => 0, 'is_demo' => false]);
        $this->overlay()->assertOk()->assertJsonPath('actual.0', 0)->assertJsonPath('variance.'.$this->target, -100);
    }

    public function test_backtest_of_same_target_does_not_supersede_operational_forecast(): void
    {
        $first = $this->prediction(100, 7);
        app(ForecastGenerationService::class)->persistForecasts(['forecast' => [['date' => $this->target, 'predicted_egg_count' => 200]]], $this->cage, null);
        $this->assertNull($first->fresh()->superseded_at);
        $this->overlay()->assertOk()->assertJsonPath('forecast.0', 100);
    }

    public function test_calendar_uses_fixed_forecasts_and_separates_historical_tests(): void
    {
        $first = $this->prediction(101, 7);
        $first->update(['superseded_at' => now()]);
        $this->prediction(202, 1);
        $this->prediction(303, -1, true);
        foreach (['saved' => 101, 'original' => 101, 'latest' => 101, 'backtest' => 303] as $view => $expected) {
            $date = \Carbon\Carbon::parse($this->target);
            $response = $this->actingAs($this->admin)->get(route('forecast', ['scope' => 'cage', 'cage' => $this->cage->cage_code, 'month' => $date->month, 'year' => $date->year, 'forecast_view' => $view]), ['Turbo-Frame' => 'production-calendar']);
            $response->assertOk();
            $forecasts = $response->viewData('forecasts');
            $this->assertCount(1, $forecasts);
            $this->assertEquals($expected, $forecasts->first()->predicted_egg_count);
        }
    }

    public function test_clear_cannot_remove_saved_forecasts(): void
    {
        $target = ReportingDateService::reportingDate()->addDays(2)->toDateString();
        $forecast = Forecast::create(['cage_id' => $this->cage->id, 'forecast_date' => ReportingDateService::reportingDateString(), 'target_date' => $target, 'predicted_egg_count' => 100]);
        $this->actingAs($this->admin)->post(route('forecast.clear'), ['scope' => 'cage', 'cage' => $this->cage->cage_code])->assertRedirect();
        $this->assertNull($forecast->fresh()->superseded_at);
        $this->assertDatabaseHas('forecasts', ['id' => $forecast->id, 'predicted_egg_count' => 100]);
        $date = \Carbon\Carbon::parse($target);
        $response = $this->actingAs($this->admin)->get(route('forecast', ['scope' => 'cage', 'cage' => $this->cage->cage_code, 'month' => $date->month, 'year' => $date->year, 'forecast_view' => 'original']), ['Turbo-Frame' => 'production-calendar']);
        $response->assertOk();
        $this->assertEquals(100, $response->viewData('forecasts')->first()->predicted_egg_count);
    }

    public function test_calendar_export_uses_fixed_forecast_from_prior_day(): void
    {
        $first = $this->prediction(101, 7);
        $first->update(['superseded_at' => now()]);
        $this->prediction(202, 1);
        $date = \Carbon\Carbon::parse($this->target);
        $response = $this->actingAs($this->admin)->post(route('forecast.csv'), ['scope' => 'cage', 'cage' => $this->cage->cage_code, 'forecast_view' => 'original', 'month' => $date->month, 'year' => $date->year]);
        $response->assertOk();
        $csv = $response->streamedContent();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertCount(2, $rows);
        $this->assertEquals(101, (float) $rows[1][1]);
    }

    public function test_overlapping_range_saves_only_missing_days(): void
    {
        $service = app(ForecastGenerationService::class);
        $start = ReportingDateService::reportingDate()->addDay();
        $first = $service->persistForecasts(['forecast' => [['date' => $start->toDateString(), 'predicted_egg_count' => 100]]], $this->cage, null)->first();
        $result = $service->persistForecasts(['forecast' => [
            ['date' => $start->toDateString(), 'predicted_egg_count' => 999],
            ['date' => $start->copy()->addDay()->toDateString(), 'predicted_egg_count' => 120],
        ]], $this->cage, null);
        $this->assertEquals([100, 120], $result->pluck('predicted_egg_count')->map(fn ($value) => (int) $value)->all());
        $this->assertEquals($first->id, $result->first()->id);
        $this->assertDatabaseCount('forecasts', 2);
        $this->assertNotNull($first->forecast_key);
    }

    public function test_already_saved_request_skips_queue_and_model_execution(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $target = ReportingDateService::reportingDate()->addDays(2)->toDateString();
        app(ForecastGenerationService::class)->persistForecasts(['forecast' => [['date' => $target, 'predicted_egg_count' => 100]]], $this->cage, null);
        $this->actingAs($this->admin)->postJson(route('forecast.generate'), ['scope' => 'cage', 'cage' => $this->cage->cage_code, 'horizon' => 1, 'start_date' => $target])
            ->assertOk()->assertJsonPath('already_saved', true);
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
        $this->assertDatabaseCount('forecast_runs', 0);
        $result = app(ForecastGenerationService::class)->generateForecast($this->cage, $this->cage->cage_code, null, collect(), 1, true, $target);
        $this->assertEquals(100, $result['forecasts']->first()->predicted_egg_count);
        $this->assertDatabaseCount('forecasts', 1);
    }

    public function test_database_rejects_two_fixed_predictions_for_the_same_key(): void
    {
        $target = ReportingDateService::reportingDate()->addDays(2)->toDateString();
        $forecast = app(ForecastGenerationService::class)->persistForecasts(['forecast' => [['date' => $target, 'predicted_egg_count' => 100]]], $this->cage, null)->first();
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Forecast::create(['cage_id' => $this->cage->id, 'forecast_date' => ReportingDateService::reportingDateString(), 'target_date' => $target, 'predicted_egg_count' => 999, 'forecast_key' => $forecast->forecast_key]);
    }

    public function test_historical_forecast_requires_explicit_backtest_mode(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('forecast.generate'), ['scope' => 'cage', 'cage' => $this->cage->cage_code, 'horizon' => 1, 'start_date' => $this->target]);
        $response->assertStatus(422);
        $this->assertStringContainsString('future day', $response->json('message'));
        $this->assertDatabaseCount('forecast_runs', 0);
    }
}
