<?php

namespace Tests\Feature;

use App\Jobs\GenerateForecastJob;
use App\Models\ForecastRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Covers the async conversion of ForecastController::generate() —
 * generation used to run the Python subprocess synchronously in the HTTP
 * request; it now creates a ForecastRun and dispatches GenerateForecastJob,
 * returning immediately. Queue::fake() is used throughout so these tests
 * verify the dispatch contract without needing the actual Python/XGBoost/
 * SARIMA environment installed.
 */
class ForecastAsyncTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
    }

    /** Seeds enough forecast_input_records rows for checkForecastDataSufficiency() to pass. */
    private function seedSufficientData(string $cageCode = 'CAGE-ASYNC-TEST', int $rows = 95): void
    {
        $data = [];
        for ($i = 0; $i < $rows; $i++) {
            $data[] = [
                'date' => now()->subDays($rows - $i)->toDateString(),
                'cage_code' => $cageCode,
                'breed' => 'ISA Brown',
                'flock_age_weeks' => 30,
                'hen_count' => 50,
                'egg_count' => 45,
                'temperature_c' => 28.0,
                'humidity_percent' => 60.0,
                'feed_consumed_kg' => 5.0,
                'mortality_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('forecast_input_records')->insert($data);
    }

    public function test_generate_with_insufficient_data_redirects_without_dispatching_job(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)->post(route('forecast.generate'), [
            'scope' => 'cage',
            'cage' => 'CAGE-NO-DATA',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('forecast_runs', 0);
    }

    public function test_generate_with_sufficient_data_creates_run_and_dispatches_job(): void
    {
        Queue::fake();
        $this->seedSufficientData('CAGE-ASYNC-TEST');

        $response = $this->actingAs($this->admin)
            ->postJson(route('forecast.generate'), [
                'scope' => 'cage',
                'cage' => 'CAGE-ASYNC-TEST',
                'horizon' => 7,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['forecast_run_id', 'status', 'poll_url']);
        $response->assertJsonPath('status', 'queued');

        $runId = $response->json('forecast_run_id');
        $this->assertDatabaseHas('forecast_runs', [
            'id' => $runId,
            'scope' => 'cage',
            'cage_code' => 'CAGE-ASYNC-TEST',
            'horizon' => 7,
            'status' => 'queued',
        ]);

        Queue::assertPushed(GenerateForecastJob::class, function ($job) use ($runId) {
            return $job->forecastRunId === $runId
                && $job->scope === 'cage'
                && $job->cageCode === 'CAGE-ASYNC-TEST'
                && $job->horizon === 7;
        });
    }

    public function test_generate_non_json_request_still_dispatches_job_but_redirects(): void
    {
        // Covers the fallback path for any non-fetch caller (plain form post,
        // GateAdminTest's bare POST) — the actual fix (non-blocking dispatch)
        // must apply regardless of what the caller asked to get back.
        Queue::fake();
        $this->seedSufficientData('CAGE-ASYNC-REDIRECT');

        $response = $this->actingAs($this->admin)->post(route('forecast.generate'), [
            'scope' => 'cage',
            'cage' => 'CAGE-ASYNC-REDIRECT',
            'horizon' => 7,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Queue::assertPushed(GenerateForecastJob::class);
        $this->assertDatabaseCount('forecast_runs', 1);
    }

    public function test_farm_scope_dispatches_job_with_null_cage_and_breed(): void
    {
        Queue::fake();
        $this->seedSufficientData('CAGE-FARM-A');
        // 'farm' scope's sufficiency check counts distinct dates across ALL
        // cages, not a specific cage_code — the 95 rows above already cover it.

        $response = $this->actingAs($this->admin)
            ->postJson(route('forecast.generate'), ['scope' => 'farm', 'horizon' => 7]);

        $response->assertOk();
        Queue::assertPushed(GenerateForecastJob::class, function ($job) {
            return $job->scope === 'farm' && $job->cageId === null && $job->breed === null;
        });
    }

    public function test_status_endpoint_reports_queued_run(): void
    {
        $run = ForecastRun::create([
            'scope' => 'cage', 'cage_code' => 'CAGE-X', 'horizon' => 7,
            'status' => 'queued', 'redirect_params' => ['scope' => 'cage', 'cage' => 'CAGE-X', 'horizon' => 7],
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('forecast.status', $run));

        $response->assertOk();
        $response->assertJson([
            'status' => 'queued',
            'error_message' => null,
            'redirect_url' => null,
        ]);
    }

    public function test_status_endpoint_reports_completed_run_with_redirect_url(): void
    {
        $run = ForecastRun::create([
            'scope' => 'cage', 'cage_code' => 'CAGE-X', 'horizon' => 7,
            'status' => 'completed',
            'redirect_params' => ['scope' => 'cage', 'cage' => 'CAGE-X', 'horizon' => 7],
            'result_metrics' => ['metrics' => ['mae' => 1.2], 'recommended_model' => 'XGBoost'],
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('forecast.status', $run));

        $response->assertOk();
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('recommended_model', 'XGBoost');
        $response->assertJsonPath('redirect_url', route('forecast', ['scope' => 'cage', 'cage' => 'CAGE-X', 'horizon' => 7]));
    }

    public function test_status_endpoint_reports_failed_run_with_error_message(): void
    {
        $run = ForecastRun::create([
            'scope' => 'cage', 'cage_code' => 'CAGE-X', 'horizon' => 7,
            'status' => 'failed', 'error_message' => 'Forecast Python environment is missing required packages.',
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('forecast.status', $run));

        $response->assertOk();
        $response->assertJsonPath('status', 'failed');
        $response->assertJsonPath('error_message', 'Forecast Python environment is missing required packages.');
        $response->assertJsonPath('redirect_url', null);
    }

    public function test_status_endpoint_returns_403_for_operator(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->firstOrFail();
        $run = ForecastRun::create(['scope' => 'cage', 'cage_code' => 'CAGE-X', 'horizon' => 7, 'status' => 'queued']);

        $response = $this->actingAs($operator)->getJson(route('forecast.status', $run));

        $response->assertForbidden();
    }

    /**
     * With QUEUE_CONNECTION=sync (phpunit.xml), dispatch() runs the job
     * inline instead of deferring it — this is what actually exercises
     * GenerateForecastJob::handle() without a running queue worker. The
     * ML/Python stack isn't installed in this environment, so the run is
     * expected to fail — what matters is that it fails *gracefully*
     * (ForecastRun marked 'failed' with a message) rather than throwing an
     * unhandled exception that would 500 the request or crash the worker.
     */
    public function test_job_runs_synchronously_under_sync_queue_and_fails_gracefully_without_python(): void
    {
        $this->seedSufficientData('CAGE-SYNC-RUN');

        $response = $this->actingAs($this->admin)
            ->postJson(route('forecast.generate'), [
                'scope' => 'cage',
                'cage' => 'CAGE-SYNC-RUN',
                'horizon' => 7,
            ]);

        $response->assertOk();
        $runId = $response->json('forecast_run_id');

        $run = ForecastRun::find($runId);
        $this->assertContains($run->status, ['completed', 'failed'], 'Job must resolve to a terminal status, not stay queued/running.');
        if ($run->status === 'failed') {
            $this->assertNotNull($run->error_message);
        }
    }
}
