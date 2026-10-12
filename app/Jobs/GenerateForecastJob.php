<?php

namespace App\Jobs;

use App\Models\Cage;
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
    public const TIMEOUT_SECONDS = 330;
    public int $timeout = self::TIMEOUT_SECONDS;
    public int $tries = 1;
    public bool $failOnTimeout = true;

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

        if ($run->status !== 'queued') {
            return;
        }
        $claimed = ForecastRun::whereKey($run->id)->where('status', 'queued')
            ->update(['status' => 'running', 'started_at' => now()]);
        if (! $claimed) {
            return;
        }
        $run->refresh();

        try {
            $cage = $this->cageId ? Cage::find($this->cageId) : null;

            $historical = match ($this->scope) {
                'farm' => $service->farmHistorical(),
                'breed' => $service->breedHistorical($this->breed),
                default => $service->cageHistorical($this->cageCode),
            };

            // Persistence keeps the first advance prediction and fills only gaps.
            $result = $service->generateForecast(
                $cage,
                $this->cageCode,
                $this->breed,
                $historical,
                $this->horizon,
                true,
                $this->startDate,
                $this->manualParams,
                $this->forecastRunId,
            );

            ForecastRun::whereKey($run->id)->where('status', 'running')->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result_metrics' => [
                    'metrics' => $result['metrics'],
                    'message' => $result['message'] ?? 'Forecast saved.',
                    'recommended_model' => $result['recommended_model'],
                ],
            ]);
        } catch (ProcessFailedException $e) {
            report($e);
            $this->markRunFailed($run, 'The forecasting engine could not finish. Please try again or ask the administrator to check the server log.');
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            $this->markRunFailed($run, 'Forecast exceeded its time limit. Please try again.');
        } catch (RuntimeException $e) {
            $this->markRunFailed($run, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->markRunFailed($run, 'Unexpected error during forecast generation.');
        }
    }

    /** Also covers worker timeouts and failures that happen outside handle(). */
    public function failed(?\Throwable $exception): void
    {
        $run = ForecastRun::find($this->forecastRunId);
        if ($run && in_array($run->status, ['queued', 'running'], true)) {
            $message = $exception instanceof \Illuminate\Queue\TimeoutExceededException
                ? 'Forecast exceeded its time limit. Please try again.'
                : 'Forecast worker stopped before completion. Please try again.';
            $this->markRunFailed($run, $message);
        }
    }

    private function markRunFailed(ForecastRun $run, string $message): void
    {
        Log::warning('Forecast generation failed', [
            'forecast_run_id' => $run->id,
            'scope' => $this->scope,
            'cage_code' => $this->cageCode,
            'breed' => $this->breed,
            'error' => $message,
        ]);

        ForecastRun::whereKey($run->id)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $message,
        ]);
    }
}
