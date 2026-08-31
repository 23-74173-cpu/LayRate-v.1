<?php

namespace App\Jobs;

use App\Models\Cage;
use App\Models\Forecast;
use App\Models\ForecastRun;
use App\Services\ForecastGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Runs the actual forecast generation that ForecastController::generate()
 * used to run inline, synchronously, in the HTTP request — a Python
 * subprocess that can take up to 300s (see
 * ForecastController::executePythonForecast()). On the Pi's built-in PHP
 * dev server (see README.md — there's no nginx/PHP-FPM in front of it),
 * that meant one forecast request could tie up a large fraction of the
 * app's total worker pool for 5 minutes, potentially starving the ~1Hz
 * sensor-ingestion endpoint. This job moves that wait off the request
 * thread entirely.
 *
 * Delegates to ForecastGenerationService (extracted from ForecastController
 * in Prompt 8) rather than duplicating ~700 lines of forecast orchestration
 * or depending on a web controller from a queue worker. The controller and
 * this job run the exact same service code path, just inside vs outside the
 * request cycle.
 *
 * Requires QUEUE_CONNECTION=database (not sync — under sync this still
 * runs inline and provides no concurrency benefit) and a supervised
 * `php artisan queue:work` process — see infra/layrate-queue-worker.service.
 */
class GenerateForecastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // A little above executePythonForecast()'s own 300s Process timeout, so
    // the job's own timeout never fires first and masks the real error.
    public int $timeout = 330;

    public function __construct(
        public int $forecastRunId,
        public string $scope,
        public string $cageCode,
        public ?int $cageId,
        public ?string $breed,
        public int $horizon,
        public ?string $startDate,
        public array $manualParams,
    ) {}

    public function handle(ForecastGenerationService $service): void
    {
        $run = ForecastRun::find($this->forecastRunId);
        if (! $run) {
            // Deleted (e.g. by an admin clearing forecast history) before
            // the worker picked it up — nothing to report to, nothing to do.
            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $cage = $this->cageId ? Cage::find($this->cageId) : null;

            [$historical, $todayDeleteQuery] = match ($this->scope) {
                'farm' => [
                    $service->farmHistorical(),
                    Forecast::whereNull('cage_id')->whereNull('breed'),
                ],
                'breed' => [
                    $service->breedHistorical($this->breed),
                    Forecast::whereNull('cage_id')->where('breed', $this->breed),
                ],
                default => [
                    $service->cageHistorical($this->cageCode),
                    $cage
                        ? Forecast::whereNull('breed')->where('cage_id', $cage->id)
                        : Forecast::whereNull('breed')->whereNull('cage_id'),
                ],
            };

            // Deliberately deleted here, right before persisting the new
            // result, rather than synchronously in the controller before
            // this job was even dispatched: that would leave a window
            // where the old forecast has already vanished from the UI but
            // the new one isn't ready yet (worse on a queue with any
            // backlog than it was when generation was synchronous). This
            // way the old forecast stays visible until the moment it's
            // replaced.
            $todayDeleteQuery->where('forecast_date', now()->toDateString())->delete();

            $result = $service->generateForecast(
                $cage,
                $this->cageCode,
                $this->breed,
                $historical,
                $this->horizon,
                true,
                $this->startDate,
                $this->manualParams,
            );

            $run->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result_metrics' => [
                    'metrics' => $result['metrics'],
                    'recommended_model' => $result['recommended_model'],
                ],
            ]);
        } catch (ProcessFailedException $e) {
            $this->fail($run, 'Forecast process failed: '.$e->getMessage());
        } catch (RuntimeException $e) {
            $this->fail($run, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->fail($run, 'Unexpected error during forecast generation.');
        }
    }

    private function fail(ForecastRun $run, string $message): void
    {
        Log::warning('Forecast generation failed', [
            'forecast_run_id' => $run->id,
            'scope' => $this->scope,
            'cage_code' => $this->cageCode,
            'breed' => $this->breed,
            'error' => $message,
        ]);

        $run->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $message,
        ]);
    }
}
