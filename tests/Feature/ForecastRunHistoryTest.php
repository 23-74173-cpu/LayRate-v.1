<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\Forecast;
use App\Models\ForecastRun;
use App\Models\User;
use App\Services\ForecastGenerationService;
use App\Services\ReportingDateService;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * Forecast history retention.
 *
 * Persistence used to delete every forecast row for the current reporting date
 * before writing the new result, so re-running a forecast destroyed the
 * previous prediction and there was never more than one run of record to
 * compare against actual production. Persistence now appends, tags each row
 * with the run that produced it, and soft-supersedes the previous prediction
 * for the same target date.
 *
 * These tests pin that behaviour: nothing is destroyed, exactly one live
 * prediction exists per (scope, target_date), and superseded rows remain
 * readable for comparison.
 */
class ForecastRunHistoryTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
    }

    public function test_a_second_run_supersedes_rather_than_deletes_the_first(): void
    {
        $target = ReportingDateService::now()->copy()->addDays(3)->toDateString();

        $first = $this->persistPrediction('CAGE-RET', $target, 100.0);
        $second = $this->persistPrediction('CAGE-RET', $target, 175.0);

        // Both rows survive: the earlier prediction is history, not garbage.
        $this->assertDatabaseHas('forecasts', [
            'id' => $first->id,
            'predicted_egg_count' => 100,
            'forecast_run_id' => $first->forecast_run_id,
        ]);
        $this->assertDatabaseHas('forecasts', [
            'id' => $second->id,
            'predicted_egg_count' => 175,
            'forecast_run_id' => $second->forecast_run_id,
        ]);

        // Exactly one is live, and it is the newer one.
        $this->assertNotNull($first->fresh()->superseded_at);
        $this->assertNull($second->fresh()->superseded_at);

        $this->assertSame(1, Forecast::live()->whereDate('target_date', $target)->count());
        $this->assertSame(
            (float) $second->predicted_egg_count,
            (float) Forecast::live()->whereDate('target_date', $target)->value('predicted_egg_count')
        );
    }

    /**
     * Supersession is keyed on target_date, so re-running one day must not
     * evict a live prediction for a different day.
     */
    public function test_supersession_does_not_evict_other_target_dates(): void
    {
        $base = ReportingDateService::now();

        $day1 = $this->persistPrediction('CAGE-RET', $base->copy()->addDays(3)->toDateString(), 100.0);
        $day2 = $this->persistPrediction('CAGE-RET', $base->copy()->addDays(4)->toDateString(), 200.0);

        $this->persistPrediction('CAGE-RET', $day1->target_date->toDateString(), 999.0);

        $this->assertNotNull($day1->fresh()->superseded_at);
        $this->assertNull($day2->fresh()->superseded_at, 'A different target date must stay live.');
    }

    /**
     * A backtest of a past date must not disturb a live forward forecast on a
     * future date — the two are independent predictions.
     */
    public function test_backtest_does_not_supersede_a_forward_forecast(): void
    {
        $today = ReportingDateService::now();
        $past = $today->copy()->subDays(10)->toDateString();
        $future = $today->copy()->addDays(5)->toDateString();

        $forward = $this->persistPrediction('CAGE-RET', $future, 300.0);
        $backtest = $this->persistPrediction('CAGE-RET', $past, 250.0);

        $this->assertNull($forward->fresh()->superseded_at);
        $this->assertFalse($forward->fresh()->is_backtest, 'A future target is not a backtest.');

        $this->assertTrue($backtest->fresh()->is_backtest, 'A past target must be flagged as a backtest.');
        $this->assertNull($backtest->fresh()->superseded_at);
    }

    /**
     * Supersession is scoped per (cage_id, breed) pair, so one cage's rerun
     * must not touch another cage's prediction for the same day.
     */
    public function test_supersession_is_scoped_to_the_cage(): void
    {
        $target = ReportingDateService::now()->copy()->addDays(3)->toDateString();

        $cageA = $this->persistPrediction('CAGE-RET', $target, 100.0);
        $cageB = $this->persistPrediction('CAGE-OTHER', $target, 400.0);

        $this->persistPrediction('CAGE-RET', $target, 150.0);

        $this->assertNotNull($cageA->fresh()->superseded_at);
        $this->assertNull($cageB->fresh()->superseded_at, 'A different cage must stay live.');
    }

    /** Every prediction points back at the run that produced it. */
    public function test_predictions_are_attributable_to_their_run(): void
    {
        $target = ReportingDateService::now()->copy()->addDays(2)->toDateString();
        $forecast = $this->persistPrediction('CAGE-RET', $target, 120.0);

        $this->assertNotNull($forecast->forecast_run_id);

        $run = $forecast->forecastRun;
        $this->assertInstanceOf(ForecastRun::class, $run);
        $this->assertSame('cage', $run->scope);
        $this->assertSame('CAGE-RET', $run->cage_code);
    }

    /**
     * Writes one prediction through the real service so the test exercises the
     * production persistence path rather than a hand-rolled insert.
     */
    private function persistPrediction(
        string $cageCode,
        string $targetDate,
        float $predicted,
    ): Forecast {
        $cage = Cage::firstOrCreate(
            ['cage_code' => $cageCode],
            [
                'location' => 'Test',
                'rows' => 1,
                'slots_per_row' => 1,
                'max_chickens_per_slot' => 50,
                'total_capacity' => 50,
                'is_active' => 1,
            ],
        );

        $run = ForecastRun::create([
            'user_id' => $this->admin->id,
            'scope' => 'cage',
            'cage_id' => $cage->id,
            'cage_code' => $cageCode,
            'horizon' => 1,
            'start_date' => $targetDate,
            'status' => 'completed',
        ]);

        $result = [
            'forecast' => [[
                'date' => $targetDate,
                'predicted_egg_count' => $predicted,
            ]],
        ];

        $collection = app(ForecastGenerationService::class)
            ->persistForecasts($result, $cage, null, $run->id);

        return $collection->first();
    }
}
