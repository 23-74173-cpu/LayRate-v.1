<?php

namespace Tests\Feature;

use App\Models\ForecastRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A run that never leaves 'queued' is not a slow forecast — it is a job no
 * worker ever claimed. QUEUE_CONNECTION=database means nothing processes
 * GenerateForecastJob unless `php artisan queue:work` is running, and that
 * failure mode is visually identical to a long generation: the browser shows
 * the progress bar creeping to 95% and then blocks for the full client poll
 * timeout. status() distinguishes the two so the UI can name the real problem.
 */
class ForecastQueueStallTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
    }

    private function makeRun(int $ageSeconds): ForecastRun
    {
        $run = ForecastRun::create([
            'user_id' => $this->admin->id,
            'scope' => 'farm',
            'horizon' => 7,
            'status' => 'queued',
        ]);

        // Backdate created_at so the age threshold is testable without
        // sleeping.
        $run->forceFill(['created_at' => now()->subSeconds($ageSeconds)])->save();

        return $run->fresh();
    }

    public function test_status_reports_stalled_when_no_worker_has_claimed_the_job(): void
    {
        $run = $this->makeRun(600);

        $response = $this->actingAs($this->admin)->getJson(route('forecast.status', $run));

        $response->assertOk();
        $response->assertJsonPath('status', 'stalled');
        $this->assertStringContainsString('queue:work', $response->json('error_message'));
    }

    /**
     * A freshly queued run is normal — the worker has simply not claimed it
     * yet — so it must not be reported as stalled.
     */
    public function test_status_does_not_stall_a_recently_queued_run(): void
    {
        $run = $this->makeRun(2);

        $response = $this->actingAs($this->admin)->getJson(route('forecast.status', $run));

        $response->assertOk();
        $response->assertJsonPath('status', 'queued');
    }

    /**
     * A busy worker legitimately holds jobs back, so a backlog is not a stall.
     * A reserved job is proof a worker is alive and working.
     *
     * The test environment runs QUEUE_CONNECTION=sync (so dispatched jobs run
     * inline and never reach a queue table at all), which also means the
     * database-driver branch has to be selected explicitly to be covered.
     */
    public function test_status_does_not_stall_while_a_worker_holds_a_reserved_job(): void
    {
        config(['queue.default' => 'database']);

        $run = $this->makeRun(600);

        // The jobs table uses integer epoch columns, not datetimes, so a
        // Carbon instance gets truncated on insert. Write the raw epoch.
        $now = time();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => $now,
            'available_at' => $now,
            'created_at' => $now,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('forecast.status', $run));

        $response->assertOk();
        $response->assertJsonPath('status', 'queued');
    }

    /** A finished run reports its own status and redirect, never 'stalled'. */
    public function test_completed_run_reports_completed(): void
    {
        $run = ForecastRun::create([
            'user_id' => $this->admin->id,
            'scope' => 'farm',
            'horizon' => 7,
            'status' => 'completed',
            'redirect_params' => ['scope' => 'farm', 'horizon' => 7],
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('forecast.status', $run));

        $response->assertOk();
        $response->assertJsonPath('status', 'completed');
        $this->assertNotNull($response->json('redirect_url'));
    }

    /** A failed run surfaces its stored error rather than a stall message. */
    public function test_failed_run_reports_its_error(): void
    {
        $run = ForecastRun::create([
            'user_id' => $this->admin->id,
            'scope' => 'farm',
            'horizon' => 7,
            'status' => 'failed',
            'error_message' => 'Forecast process failed: python exploded',
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('forecast.status', $run));

        $response->assertOk();
        $response->assertJsonPath('status', 'failed');
        $response->assertJsonPath('error_message', 'Forecast process failed: python exploded');
    }

    /**
     * A deleted or never-existed run must 404 rather than report a fake status.
     *
     * The client polls this endpoint in a loop. A 404 JSON body carries no
     * `status` field, so if it ever returned one the poller would fall through
     * every branch and keep re-scheduling itself until the client deadline —
     * which is exactly what happened when a stale page kept polling a run that
     * had been cleared, producing minutes of silent polling with no outcome.
     * The 404 is the signal the client needs to stop.
     */
    public function test_missing_run_returns_404_not_a_synthetic_status(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('forecast.status', 999999));

        $response->assertNotFound();
        $response->assertJsonMissing(['status' => 'queued']);
        $response->assertJsonMissing(['status' => 'running']);
    }
}
