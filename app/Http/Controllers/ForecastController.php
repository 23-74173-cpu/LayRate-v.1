<?php

namespace App\Http\Controllers;

use App\Exports\ForecastExport;
use App\Exports\ProductionDataExport;
use App\Forecast\ForecastRules;
use App\Jobs\GenerateForecastJob;
use App\Models\Cage;
use App\Models\Forecast;
use App\Models\ForecastRun;
use App\Services\ForecastGenerationService;
use App\Services\ReportingDateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ForecastController extends Controller
{
    /**
     * How long a run may sit unclaimed before status() reports it as stalled
     * rather than merely slow. A healthy worker claims a job in well under a
     * second, so this is generous; it exists to absorb queue backlog, not to
     * mask a missing worker.
     */
    private const QUEUE_STALL_SECONDS = 45;

    /**
     * Predictions the calendar is allowed to display.
     *
     * Forward predictions are scoped to the current reporting date, because
     * tomorrow's forecast really is a different thing from today's and
     * yesterday's is stale. Backtests must not be scoped that way: they are
     * fixed claims about days that have already happened, so filtering them by
     * the day they happened to be generated made a whole scored month vanish
     * from the calendar overnight even though every row was still in the
     * table. A backtest therefore stays visible no matter when it was run.
     */
    private function visibleForecastsQuery()
    {
        return Forecast::live()->where(function ($query) {
            $query->where('forecast_date', ReportingDateService::reportingDateString())
                ->orWhere('is_backtest', true);
        });
    }

    /**
     * The predictions a calendar month should render: today's forward forecast
     * plus every backtest whose target date falls inside the month on screen.
     *
     * The two halves are gathered separately on purpose. A single query with a
     * row limit cannot serve both, because ordering by target_date puts the
     * historical backtest rows first — the limit would be spent entirely on
     * them and the forward forecast would disappear from the calendar the
     * moment a backtested month was in range. Forward behaviour is therefore
     * left exactly as it was, and backtests are added around it.
     *
     * @param  callable(\Illuminate\Database\Eloquent\Builder): void  $applyScope  narrows to farm / breed / cage
     */
    private function forecastsForCalendar(callable $applyScope, Carbon $calendarDate, int $horizon): Collection
    {
        $forward = Forecast::live()
            ->where('forecast_date', ReportingDateService::reportingDateString())
            ->where('is_backtest', false)
            ->whereNotNull('target_date');
        $applyScope($forward);
        $forward = $forward->orderBy('target_date')->limit($horizon)->get();

        $backtest = Forecast::live()
            ->where('is_backtest', true)
            ->whereNotNull('target_date')
            ->whereBetween('target_date', [
                $calendarDate->copy()->startOfMonth()->toDateString(),
                $calendarDate->copy()->endOfMonth()->toDateString(),
            ]);
        $applyScope($backtest);
        $backtest = $backtest->orderBy('target_date')->get();

        return $forward->concat($backtest)->values();
    }

    private function forecastService(): ForecastGenerationService
    {
        return app(ForecastGenerationService::class);
    }

    public function index(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $scope = $request->get('scope', 'cage');
        $horizon = (int) $request->get('horizon', 7);

        $calendarYear = (int) $request->get('year', ReportingDateService::now()->year);
        $calendarMonth = (int) $request->get('month', ReportingDateService::now()->month);
        $calendarDate = ReportingDateService::now()->copy()->setDate($calendarYear, max(1, min(12, $calendarMonth)), 1);

        $allCages = $this->forecastService()->recordedCages();
        $allBreeds = $this->forecastService()->recordedBreeds();

        $cageCode = $request->get('cage', $allCages->first() ?? '');
        $breed = $request->get('breed');

        if ($scope === 'breed' && empty($breed)) {
            $breed = $allBreeds->first();
        }

        $metrics = session('forecast_metrics');
        $recommendedModel = session('recommended_model');

        $dataSufficiency = $this->checkForecastDataSufficiency($scope, $cageCode, $breed);
        $hasEnoughData = $dataSufficiency['has_enough'];

        $productionByDate = $this->calendarProduction($scope, $cageCode, $breed, $calendarDate);

        $calendarMinYear = $this->calendarMinYear();

        $forecastMinDate = ForecastRules::minStartDate($this->earliestProductionDate())->toDateString();

        Log::info('Forecast index page load', [
            'scope' => $scope,
            'cage_code' => $cageCode,
            'breed' => $breed,
            'has_enough_data' => $hasEnoughData,
            'forecast_data_days' => $dataSufficiency['current_count'],
            'all_cages_count' => $allCages->count(),
            'all_cages' => $allCages->toArray(),
            'all_breeds' => $allBreeds->toArray(),
            'horizon' => $horizon,
        ]);

        if ($scope === 'farm') {
            $historical = $this->forecastService()->farmHistorical();
            $forecasts = $this->forecastsForCalendar(
                fn ($q) => $q->whereNull('cage_id')->whereNull('breed'),
                $calendarDate,
                $horizon,
            );

            $viewData = compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate', 'dataSufficiency', 'productionByDate', 'calendarMinYear', 'forecastMinDate')
                + ['forecastDataDays' => $dataSufficiency['current_count'], 'breed' => $breed];

            if ($request->header('Turbo-Frame') === 'production-calendar') {
                return view('forecast._calendar', $viewData);
            }

            if ($request->header('Turbo-Frame') === 'forecast-workspace') {
                return view('forecast._workspace', $viewData);
            }

            return view('forecast', $viewData)
                ->with('label', 'Whole Farm');
        }

        if ($scope === 'breed' && $breed) {
            $historical = $this->forecastService()->breedHistorical($breed);
            $forecasts = $this->forecastsForCalendar(
                fn ($q) => $q->whereNull('cage_id')->where('breed', $breed),
                $calendarDate,
                $horizon,
            );

            $viewData = compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate', 'dataSufficiency', 'productionByDate', 'calendarMinYear', 'forecastMinDate')
                + ['forecastDataDays' => $dataSufficiency['current_count']];

            if ($request->header('Turbo-Frame') === 'production-calendar') {
                return view('forecast._calendar', $viewData);
            }

            if ($request->header('Turbo-Frame') === 'forecast-workspace') {
                return view('forecast._workspace', $viewData);
            }

            return view('forecast', $viewData)
                ->with('label', $breed);
        }

        $cage = Cage::where('cage_code', $cageCode)->first();

        $historical = $this->forecastService()->cageHistorical($cageCode);

        $forecasts = $this->forecastsForCalendar(
            fn ($q) => $q->when($cage, fn ($q) => $q->where('cage_id', $cage->id))
                ->when(! $cage, fn ($q) => $q->whereNull('cage_id'))
                ->whereNull('breed'),
            $calendarDate,
            $horizon,
        );

        $viewData = compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate', 'dataSufficiency', 'productionByDate', 'calendarMinYear', 'forecastMinDate')
            + ['forecastDataDays' => $dataSufficiency['current_count'], 'breed' => $breed];

        if ($request->header('Turbo-Frame') === 'production-calendar') {
            return view('forecast._calendar', $viewData);
        }

        if ($request->header('Turbo-Frame') === 'forecast-workspace') {
            return view('forecast._workspace', $viewData);
        }

        return view('forecast', $viewData);
    }

    /**
     * Actual recorded production for the visible calendar grid, keyed by
     * 'Y-m-d'.
     *
     * The grid renders up to 6 leading days from the previous month and up to
     * 6 trailing days from the next one, so the query is padded by a week on
     * each side — otherwise the spillover cells would always show as empty even
     * when those dates have real production logs.
     */
    private function calendarProduction(string $scope, ?string $cageCode, ?string $breed, Carbon $calendarDate): Collection
    {
        $rangeStart = $calendarDate->copy()->startOfMonth()->subDays(7)->toDateString();
        $rangeEnd = $calendarDate->copy()->endOfMonth()->addDays(7)->toDateString();

        return $this->forecastService()->productionForRange(
            $scope,
            $rangeStart,
            $rangeEnd,
            $cageCode,
            $breed
        );
    }

    /**
     * First year the Production Calendar can navigate back to.
     *
     * Past months are now browsable (they hold the actual production data the
     * forecast will eventually be compared against), so the dropdown needs a
     * lower bound. It is derived from the earliest real production log and
     * falls back to the current year when nothing has been recorded yet. A
     * hard 5-year floor keeps the list sane if the seed data ever goes back
     * further than that.
     */
    private function calendarMinYear(): int
    {
        $earliest = $this->forecastService()->earliestProductionDate();
        $currentYear = (int) ReportingDateService::now()->format('Y');

        if ($earliest === null) {
            return $currentYear;
        }

        return max($currentYear - 5, (int) substr($earliest, 0, 4));
    }

    /**
     * Earliest date holding real production, or null when nothing has been
     * recorded. Used to bound how far back a forecast may target — a backtest
     * only makes sense for a day that has actuals to be scored against.
     */
    private function earliestProductionDate(): ?Carbon
    {
        $earliest = $this->forecastService()->earliestProductionDate();

        return $earliest === null ? null : Carbon::parse($earliest)->startOfDay();
    }

    /**
     * Forecast pages are admin-only. A non-admin who reaches a forecast URL
     * (e.g. by typing /forecast directly) is redirected back to the module
     * they were on, or to the dashboard when there is no safe referrer.
     */
    private function ensureAdminOrRedirect(Request $request): ?RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return null;
        }

        $referer = $request->headers->get('referer');
        if ($referer && ! str_contains($referer, '/forecast')) {
            return redirect()->to($referer);
        }

        return redirect()->route('dashboard');
    }

    public function downloadTemplate(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        try {
            $pythonBinary = $this->forecastService()->resolvePythonBinary();
            $scriptPath = base_path('forecast-api/generate_forecast_sheet.py');
            $outputName = 'forecast_input_'.now()->format('Ymd_His').'.xlsx';
            $outputPath = base_path('forecast-api/'.$outputName);

            if (! file_exists($scriptPath)) {
                throw new RuntimeException('Forecast sheet generator not found at: '.$scriptPath);
            }

            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $command = [
                $pythonBinary,
                $scriptPath,
                '--output', $outputName,
            ];

            if ($startDate && $endDate) {
                $command[] = '--start-date';
                $command[] = $startDate;
                $command[] = '--end-date';
                $command[] = $endDate;
            } else {
                $command[] = '--days';
                $command[] = '90';
            }

            $process = new Process($command, base_path('forecast-api'));
            $process->setTimeout(120);
            $process->setEnv($this->forecastService()->processEnv());
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            if (! file_exists($outputPath)) {
                throw new RuntimeException('Forecast sheet file was not created.');
            }

            return response()->download($outputPath, $outputName)->deleteFileAfterSend(true);
        } catch (ProcessFailedException $e) {
            return redirect()->back()
                ->with('error', 'Forecast sheet generation failed: '.$e->getMessage());
        } catch (RuntimeException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    public function generate(Request $request)
    {
        $scope = $request->get('scope', 'cage');
        $horizon = (int) $request->get('horizon', 7);
        $breed = $request->get('breed');

        Log::info('Forecast generate request', [
            'scope' => $scope,
            'cage' => $request->get('cage'),
            'breed' => $breed,
            'horizon' => $horizon,
            'start_date' => $request->input('start_date'),
        ]);

        // Horizon bound (Prompt 15): the workspace radios offer 1/7/14/30 but
        // day-range selection legitimately submits any 1-30 value, so the
        // bound is the continuous range, not the discrete set. Anything
        // outside it (e.g. horizon=99 far-future predictions, 0/negative
        // garbage) is rejected here, upstream of both the plain and the
        // start_date paths, mirroring the redirect-back guards below.
        if ($horizon < 1 || $horizon > 30) {
            return $this->rejectGenerate($request, 'Invalid forecast horizon. Choose between 1 and 30 days.');
        }

        $cageCode = $request->get('cage', $this->forecastService()->recordedCages()->first() ?? '');

        if ($scope === 'breed' && empty($breed)) {
            $breed = $this->forecastService()->recordedBreeds()->first();
        }

        $startDate = $request->input('start_date');

        if ($startDate) {
            // A calendar/date-specific forecast can span multiple consecutive
            // days (e.g. drag-selecting Aug 5-10). Honor the requested horizon,
            // defaulting to a single day when none is supplied.
            $horizon = max(1, (int) $request->get('horizon', 1));

            try {
                $parsed = Carbon::parse($startDate);

                $minDate = ForecastRules::minStartDate($this->earliestProductionDate());

                if ($parsed->lt($minDate)) {
                    return $this->rejectGenerate($request, "Forecast date must be on or after {$minDate->toDateString()}.");
                }
                if ($parsed->gt(ForecastRules::maxStartDate())) {
                    return $this->rejectGenerate($request, 'Forecast date cannot exceed 30 days from today.');
                }

                $rangeEnd = $parsed->copy()->addDays($horizon - 1)->endOfDay();

                // A range may legitimately straddle the reporting date: the
                // past part gets backtested against actuals and the future
                // part is a forward prediction. Because the horizon is already
                // capped at 30 days, an anchor in the past can never push the
                // end past the +30 day ceiling, so one flat check covers both
                // directions.
                if ($rangeEnd->gt(ForecastRules::maxStartDate())) {
                    return $this->rejectGenerate($request, 'Forecast range cannot extend beyond 30 days from today.');
                }
            } catch (\Exception $e) {
                return $this->rejectGenerate($request, 'Invalid forecast date.');
            }
        }

        // A backtest is trained on production strictly before its target day, so
        // the sufficiency count is bounded the same way the Python training cut
        // is. Counting records from after the target would validate a run
        // against data the model never gets to see.
        $sufficiencyBefore = ($startDate && ForecastRules::isBacktest($startDate))
            ? Carbon::parse($startDate)->toDateString()
            : null;

        $dataSufficiency = $this->checkForecastDataSufficiency($scope, $cageCode, $breed, $sufficiencyBefore);
        if (! $dataSufficiency['has_enough']) {
            $message = $sufficiencyBefore
                ? "Need at least 90 days of production records before {$sufficiencyBefore} to backtest that date. Currently have {$dataSufficiency['current_count']} days ({$dataSufficiency['days_remaining']} remaining)."
                : "Need at least 90 days of production records to generate a forecast. Currently have {$dataSufficiency['current_count']} days ({$dataSufficiency['days_remaining']} remaining).";

            return $this->rejectGenerate($request, $message);
        }

        // Everything above is fast, synchronous validation — kept exactly as
        // it was, so bad input still fails instantly. Everything below used
        // to run the Python subprocess synchronously too (up to 300s per
        // ForecastRules/executePythonForecast's own timeout), inside this
        // same HTTP request. It now only resolves which cage/historical
        // scope applies and hands off to GenerateForecastJob — see that
        // class and ForecastRun for the rest of the flow. The actual
        // generation logic (generateForecast/executePythonForecast/
        // persistForecasts) is unchanged; the job just calls the same
        // methods this method used to call directly.
        $manualParams = $this->collectManualParams($request);

        if ($scope === 'farm') {
            $redirectParams = $startDate
                ? ['scope' => 'farm', 'horizon' => $horizon, 'start_date' => $startDate, 'month' => Carbon::parse($startDate)->month, 'year' => Carbon::parse($startDate)->year]
                : ['scope' => 'farm', 'horizon' => $horizon];

            $forecastRun = ForecastRun::create([
                'user_id' => $request->user()?->id,
                'scope' => 'farm',
                'horizon' => $horizon,
                'start_date' => $startDate,
                'redirect_params' => $redirectParams,
                // Set explicitly rather than relying on the migration's
                // DB-side default: Eloquent doesn't re-fetch column defaults
                // into the in-memory model after INSERT, so ->status would
                // otherwise read as null here even though the DB row is
                // correctly 'queued' — respondQueued() below serializes this
                // same in-memory instance into the JSON response.
                'status' => 'queued',
            ]);

            GenerateForecastJob::dispatch($forecastRun->id, 'farm', 'ALL', null, null, $horizon, $startDate, $manualParams);

            return $this->respondQueued($request, $forecastRun, 'Whole-farm forecast');
        }

        if ($scope === 'breed' && $breed) {
            $redirectParams = $startDate
                ? ['scope' => 'breed', 'breed' => $breed, 'horizon' => $horizon, 'start_date' => $startDate, 'month' => Carbon::parse($startDate)->month, 'year' => Carbon::parse($startDate)->year]
                : ['scope' => 'breed', 'breed' => $breed, 'horizon' => $horizon];

            $forecastRun = ForecastRun::create([
                'user_id' => $request->user()?->id,
                'scope' => 'breed',
                'breed' => $breed,
                'horizon' => $horizon,
                'start_date' => $startDate,
                'redirect_params' => $redirectParams,
                'status' => 'queued', // see the farm-scope branch above for why this is explicit
            ]);

            GenerateForecastJob::dispatch($forecastRun->id, 'breed', 'ALL', null, $breed, $horizon, $startDate, $manualParams);

            return $this->respondQueued($request, $forecastRun, "{$breed} forecast");
        }

        $cage = Cage::where('cage_code', $cageCode)->first();

        $redirectParams = $startDate
            ? ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon, 'start_date' => $startDate, 'month' => Carbon::parse($startDate)->month, 'year' => Carbon::parse($startDate)->year]
            : ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon];

        $forecastRun = ForecastRun::create([
            'user_id' => $request->user()?->id,
            'scope' => 'cage',
            'cage_id' => $cage?->id,
            'cage_code' => $cageCode,
            'horizon' => $horizon,
            'start_date' => $startDate,
            'redirect_params' => $redirectParams,
            'status' => 'queued', // see the farm-scope branch above for why this is explicit
        ]);

        GenerateForecastJob::dispatch($forecastRun->id, 'cage', $cageCode, $cage?->id, null, $horizon, $startDate, $manualParams);

        return $this->respondQueued($request, $forecastRun, 'Forecast');
    }

    /**
     * Response for a just-queued forecast run. JSON (forecast_run_id +
     * poll_url) for the fetch-based submit in forecast.blade.php/
     * _calendar.blade.php, which polls GET /forecast/status/{id} and
     * Turbo-visits redirect_params' URL once status flips to completed.
     * Falls back to a plain redirect for any caller that doesn't ask for
     * JSON (e.g. a non-JS form post, or GateAdminTest's bare POST) — that
     * caller won't see the result appear automatically, but the request
     * still returns immediately either way, which is the actual fix here.
     */
    /**
     * Response for a validation failure on forecast.generate.
     *
     * The fetch-based submit in forecast.blade.php always sends
     * `Accept: application/json` and does `response.json()` on any 2xx, so
     * answering these with a bare `redirect()->back()` (a 302 the browser
     * follows to an HTML page) makes the client throw
     * `Unexpected token '<', "<!DOCTYPE"... is not valid JSON` — the real
     * message ("Invalid forecast horizon. Choose between 1 and 30 days.")
     * is thrown away and the user sees a parse error instead.
     *
     * Returning 422 JSON for JSON clients lets the existing `!response.ok`
     * branch surface `body.message` as a toast. Non-JSON callers (plain form
     * posts, tests that POST without an Accept header) keep the redirect-back
     * behaviour they had before.
     */
    private function rejectGenerate(Request $request, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return redirect()->back()
            ->with('error', $message)
            ->withInput();
    }

    private function respondQueued(Request $request, ForecastRun $forecastRun, string $label)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'forecast_run_id' => $forecastRun->id,
                'status' => $forecastRun->status,
                'poll_url' => route('forecast.status', $forecastRun),
            ]);
        }

        return redirect()->route('forecast', $forecastRun->redirect_params)
            ->with('success', "{$label} generation started — this can take a few minutes.");
    }

    /**
     * Poll target for an in-flight forecast run. Mirrors the admin-only
     * protection on forecast.generate itself.
     *
     * A run that stays 'queued' means no worker has claimed the job. The usual
     * cause is simply that `php artisan queue:work` is not running, which
     * otherwise looks identical to a slow forecast: the browser shows a
     * progress bar creeping toward 95% and then waits out the full client-side
     * poll timeout, so a job that will never start is reported as one that is
     * "taking longer than expected". Reporting a distinct 'stalled' status
     * lets the UI name the actual problem.
     */
    public function status(ForecastRun $forecastRun)
    {
        $stalled = $forecastRun->status === 'queued'
            && $forecastRun->created_at->lt(now()->subSeconds(self::QUEUE_STALL_SECONDS))
            && ! $this->queueWorkerIsBusy();

        return response()->json([
            'status' => $stalled ? 'stalled' : $forecastRun->status,
            'error_message' => $stalled
                ? 'No queue worker is running, so this forecast has not started. Start one with "php artisan queue:work" and try again.'
                : $forecastRun->error_message,
            'metrics' => $forecastRun->result_metrics['metrics'] ?? null,
            'recommended_model' => $forecastRun->result_metrics['recommended_model'] ?? null,
            'redirect_url' => $forecastRun->status === 'completed'
                ? route('forecast', $forecastRun->redirect_params ?? [])
                : null,
        ]);
    }

    /**
     * Whether any worker currently holds a reserved job.
     *
     * This is what distinguishes "worker is busy with an earlier long run, so
     * yours is legitimately waiting its turn" from "no worker exists at all".
     * Only the database driver is inspectable this way; for anything else
     * (notably sync, where the job already ran inline) this reports false and
     * the age threshold alone governs.
     */
    private function queueWorkerIsBusy(): bool
    {
        $connection = config('queue.default');
        $config = config("queue.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'database') {
            return false;
        }

        return DB::connection($config['connection'] ?? config('database.default'))
            ->table($config['table'] ?? 'jobs')
            ->whereNotNull('reserved_at')
            ->exists();
    }

    public function import(Request $request)
    {
        $isAjax = $request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        try {
            $validated = $request->validate([
                'forecast_file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            ]);

            $file = $validated['forecast_file'];
            $fullPath = $file->getRealPath();

            if (! $fullPath || ! file_exists($fullPath)) {
                throw new RuntimeException('Uploaded file not found at: '.($fullPath ?: 'unknown path'));
            }

            $pythonBinary = $this->forecastService()->resolvePythonBinary();
            $scriptPath = base_path('forecast-api/import_forecast_input.py');

            Log::info('Forecast import (single-phase) starting', [
                'python' => $pythonBinary,
                'script' => $scriptPath,
                'file_path' => $fullPath,
                'python_exists' => file_exists($pythonBinary),
                'script_exists' => file_exists($scriptPath),
                'file_exists' => file_exists($fullPath),
            ]);

            if (! file_exists($scriptPath)) {
                throw new RuntimeException('Forecast import script not found at: '.$scriptPath);
            }

            $command = [
                $pythonBinary,
                $scriptPath,
                $fullPath,
                '--source-file',
                $file->getClientOriginalName(),
            ];

            $process = new Process($command, base_path());
            $process->setTimeout(300);
            $process->setEnv($this->forecastService()->processEnv());
            $process->run();

            Log::info('Forecast import (single-phase) process result', [
                'exit_code' => $process->getExitCode(),
                'stdout' => trim($process->getOutput()),
                'stderr' => trim($process->getErrorOutput()),
                'successful' => $process->isSuccessful(),
            ]);

            if (! $process->isSuccessful()) {
                $errorOutput = trim($process->getErrorOutput());
                $stdOutput = trim($process->getOutput());
                $detail = $errorOutput ?: $stdOutput;

                Log::error('Forecast import Python process failed', [
                    'python' => $pythonBinary,
                    'script' => $scriptPath,
                    'file_path' => $fullPath,
                    'file_exists' => file_exists($fullPath),
                    'exit_code' => $process->getExitCode(),
                    'stdout' => $stdOutput,
                    'stderr' => $errorOutput,
                ]);

                throw new RuntimeException(
                    'Import process failed.'.($detail ? ' '.$detail : '')
                );
            }

            $output = trim($process->getOutput());
            $count = 0;
            if (preg_match('/Imported (\d+) row/', $output, $matches)) {
                $count = (int) $matches[1];
            }

            $message = "Imported {$count} production record(s) successfully.";

            if ($isAjax) {
                session()->flash('success', $message);

                return response()->json(['success' => true, 'message' => $message, 'count' => $count]);
            }

            return redirect()->back()->with('success', $message);
        } catch (Illuminate\Validation\ValidationException $e) {
            if ($isAjax) {
                return response()->json(['success' => false, 'errors' => $e->errors()], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (ProcessFailedException $e) {
            $message = 'Forecast import failed: '.$e->getMessage();
            if ($isAjax) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($isAjax) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }
    }

    /**
     * Phase 1: Parse the uploaded file and return preview metadata without writing to DB.
     *
     * The file is saved to a temporary path so phase 2 (confirm) can pick it up.
     * Returns JSON with total_rows, valid_rows, invalid_rows, date_range, and a
     * temp_path the client must pass back when confirming.
     */
    public function importPreview(Request $request)
    {
        try {
            $validated = $request->validate([
                'forecast_file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            ]);

            $file = $validated['forecast_file'];
            $fullPath = $file->getRealPath();

            if (! $fullPath || ! file_exists($fullPath)) {
                throw new RuntimeException('Uploaded file not found.');
            }

            // Persist the upload to a temp directory so the confirm step can read it.
            $tempDir = storage_path('app/private/forecast-imports');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0775, true);
            }
            $tempName = 'import_'.bin2hex(random_bytes(16)).'.xlsx';
            $tempPath = $tempDir.'/'.$tempName;
            $file->move($tempDir, $tempName);

            $pythonBinary = $this->forecastService()->resolvePythonBinary();
            $scriptPath = base_path('forecast-api/import_forecast_input.py');

            Log::info('Forecast preview starting', [
                'python' => $pythonBinary,
                'script' => $scriptPath,
                'temp_path' => $tempPath,
                'python_exists' => file_exists($pythonBinary),
                'script_exists' => file_exists($scriptPath),
            ]);

            if (! file_exists($scriptPath)) {
                throw new RuntimeException('Forecast import script not found.');
            }

            $command = [$pythonBinary, $scriptPath, $tempPath, '--preview'];
            $process = new Process($command, base_path());
            $process->setTimeout(120);
            $process->setEnv($this->forecastService()->processEnv());
            $process->run();

            if (! $process->isSuccessful()) {
                $errorOutput = trim($process->getErrorOutput());
                $stdOutput = trim($process->getOutput());
                // Python preview script may emit {"error": "..."} as JSON on failure.
                $detail = $errorOutput;
                if (! $detail && $stdOutput) {
                    $decoded = json_decode($stdOutput, true);
                    $detail = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : $stdOutput;
                }
                Log::error('Forecast preview process failed', [
                    'exit_code' => $process->getExitCode(),
                    'stderr' => $errorOutput,
                    'stdout' => $stdOutput,
                ]);
                throw new RuntimeException('Preview failed. '.$detail);
            }

            $json = json_decode(trim($process->getOutput()), true);
            if (! is_array($json)) {
                throw new RuntimeException('Invalid preview output from Python script.');
            }

            $json['temp_path'] = $tempPath;
            $json['source_file'] = $file->getClientOriginalName();

            return response()->json($json);
        } catch (Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Forecast preview failed', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Phase 2: Execute the actual import using the temp file from phase 1.
     *
     * Expects JSON body: { temp_path: string, source_file: string }
     * Returns JSON with success, count, and message.
     */
    public function importConfirm(Request $request)
    {
        try {
            $validated = $request->validate([
                'temp_path' => ['required', 'string'],
                'source_file' => ['required', 'string'],
            ]);

            $tempPath = $validated['temp_path'];
            $sourceFile = $validated['source_file'];

            // Security: ensure the path is inside our temp directory.
            $tempDir = realpath(storage_path('app/private/forecast-imports'));
            $realPath = realpath($tempPath);
            $normDir = str_replace('\\', '/', $tempDir ?? '');
            $normFile = str_replace('\\', '/', $realPath ?? '');
            if ($tempDir === false || $realPath === false || ! str_starts_with($normFile, $normDir.'/')) {
                throw new RuntimeException('Invalid or expired import session.');
            }

            if (! file_exists($realPath)) {
                throw new RuntimeException('Import file not found. Please re-upload.');
            }

            $pythonBinary = $this->forecastService()->resolvePythonBinary();
            $scriptPath = base_path('forecast-api/import_forecast_input.py');

            $command = [
                $pythonBinary, $scriptPath, $realPath,
                '--source-file', $sourceFile,
            ];

            Log::info('Forecast import confirm starting', [
                'python' => $pythonBinary,
                'script' => $scriptPath,
                'real_path' => $realPath,
                'source_file' => $sourceFile,
                'python_exists' => file_exists($pythonBinary),
                'script_exists' => file_exists($scriptPath),
                'file_exists' => file_exists($realPath),
            ]);

            $process = new Process($command, base_path());
            $process->setTimeout(300);
            $process->setEnv($this->forecastService()->processEnv());
            $process->run();

            Log::info('Forecast import confirm process result', [
                'exit_code' => $process->getExitCode(),
                'stdout' => trim($process->getOutput()),
                'stderr' => trim($process->getErrorOutput()),
                'successful' => $process->isSuccessful(),
            ]);

            // Clean up temp file regardless of outcome.
            @unlink($realPath);

            if (! $process->isSuccessful()) {
                $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());
                Log::error('Forecast import confirm failed', [
                    'exit_code' => $process->getExitCode(),
                    'stderr' => $error,
                ]);
                throw new RuntimeException('Import failed. '.$error);
            }

            $output = trim($process->getOutput());
            $count = 0;
            if (preg_match('/Imported (\d+) row/', $output, $matches)) {
                $count = (int) $matches[1];
            }

            $message = "Imported {$count} production record(s) successfully.";

            return response()->json(['success' => true, 'count' => $count, 'message' => $message]);
        } catch (Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Forecast import confirm failed', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Build a manual parameter payload if all required fields are present.
     */
    private function collectManualParams(?Request $request): array
    {
        if (! $request || $request->input('mode') !== 'manual') {
            return [];
        }

        $manualFields = [
            'manual_breed' => $request->input('manual_breed'),
            'live_hens' => $request->input('live_hens'),
            'flock_age_weeks' => $request->input('flock_age_weeks'),
            'temperature_c' => $request->input('temperature_c'),
            'humidity_percent' => $request->input('humidity_percent'),
            'crude_protein_percent' => $request->input('crude_protein_percent'),
            'total_feed_consumed_kg' => $request->input('total_feed_consumed_kg'),
            'monthly_mortality' => $request->input('monthly_mortality'),
            'heat_stress' => $request->input('heat_stress'),
        ];

        $filled = array_filter($manualFields, fn ($v) => $v !== null && $v !== '');

        return count($filled) === count($manualFields) ? $filled : [];
    }

    /**
     * Determine whether the aggregated production tables have enough historical
     * data for the requested scope. Delegates to ForecastGenerationService,
     * which computes the counts directly from the native tables.
     *
     * Whole farm needs at least 90 distinct dates. Per-cage / per-breed need
     * at least 90 rows for the selected cage or breed.
     */
    private function checkForecastDataSufficiency(string $scope, ?string $cageCode = null, ?string $breed = null, ?string $beforeDate = null): array
    {
        return $this->forecastService()->dataSufficiency($scope, $cageCode, $breed, $beforeDate);
    }

    /**
     * Clear the current forward forecast from the calendar.
     *
     * Backtest rows are deliberately left untouched. They are scored history —
     * the entire point of generating them is to compare each prediction against
     * the actual later — so this used to hard-delete a whole backtested month
     * outright, with no supersede and no way back. "Clear forecast" means
     * "drop today's forward predictions", not "erase my record".
     */
    public function clear(Request $request)
    {
        $scope = $request->get('scope', 'cage');
        $breed = $request->get('breed');
        $cageCode = $request->get('cage', 'ALL');

        $query = $this->visibleForecastsQuery()
            ->where('forecast_date', ReportingDateService::reportingDateString())
            ->where('is_backtest', false);

        if ($scope === 'farm') {
            $query->whereNull('cage_id')->whereNull('breed');
        } elseif ($scope === 'breed' && $breed) {
            $query->whereNull('cage_id')->where('breed', $breed);
        } else {
            $cage = Cage::where('cage_code', $cageCode)->first();
            if ($cage) {
                $query->where('cage_id', $cage->id)->whereNull('breed');
            } else {
                $query->whereNull('cage_id')->whereNull('breed');
            }
        }

        $deleted = $query->delete();
        $successMessage = $deleted > 0 ? 'Forecast cleared from the calendar.' : 'No forecast to clear for the current selection.';

        if ($this->wantsTurboStream($request)) {
            $viewData = $this->buildForecastViewData($request);
            $viewData['successMessage'] = $successMessage;

            session()->flash('forecast_generated', false);

            return $this->renderTurboStream($viewData);
        }

        return redirect()->back()
            ->with('success', $successMessage)
            ->with('forecast_generated', false);
    }

    private function wantsTurboStream(Request $request): bool
    {
        $accept = $request->header('Accept', '');

        return str_contains($accept, 'text/vnd.turbo-stream.html');
    }

    private function renderTurboStream(array $viewData): Response
    {
        $workspaceHtml = view('forecast._workspace', $viewData)->render();
        $calendarHtml = view('forecast._calendar', $viewData)->render();

        $stream = '';
        $stream .= '<turbo-stream action="replace" target="forecast-workspace"><template>'.$workspaceHtml.'</template></turbo-stream>';
        $stream .= '<turbo-stream action="replace" target="production-calendar"><template>'.$calendarHtml.'</template></turbo-stream>';

        return response($stream)->header('Content-Type', 'text/vnd.turbo-stream.html');
    }

    /**
     * Build the view data array shared by index, clear, and respondAfterGenerate.
     */
    private function buildForecastViewData(Request $request): array
    {
        $scope = $request->get('scope', 'cage');
        $horizon = (int) $request->get('horizon', 7);

        $calendarYear = (int) $request->get('year', ReportingDateService::now()->year);
        $calendarMonth = (int) $request->get('month', ReportingDateService::now()->month);
        $calendarDate = ReportingDateService::now()->copy()->setDate($calendarYear, max(1, min(12, $calendarMonth)), 1);

        $allCages = $this->forecastService()->recordedCages();
        $allBreeds = $this->forecastService()->recordedBreeds();

        $cageCode = $request->get('cage', $allCages->first() ?? '');
        $breed = $request->get('breed');

        if ($scope === 'breed' && empty($breed)) {
            $breed = $allBreeds->first();
        }

        $dataSufficiency = $this->checkForecastDataSufficiency($scope, $cageCode, $breed);
        $hasEnoughData = $dataSufficiency['has_enough'];

        $productionByDate = $this->calendarProduction($scope, $cageCode, $breed, $calendarDate);

        $calendarMinYear = $this->calendarMinYear();

        $forecastMinDate = ForecastRules::minStartDate($this->earliestProductionDate())->toDateString();

        $historical = collect();
        $forecasts = collect();
        $metrics = null;
        $recommendedModel = null;

        if ($scope === 'farm') {
            $historical = $this->forecastService()->farmHistorical();
            $forecasts = $this->forecastsForCalendar(
                fn ($q) => $q->whereNull('cage_id')->whereNull('breed'),
                $calendarDate,
                $horizon,
            );

            return compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate', 'dataSufficiency', 'productionByDate', 'calendarMinYear', 'forecastMinDate')
                + ['forecastDataDays' => $dataSufficiency['current_count'], 'breed' => $breed];
        }

        if ($scope === 'breed' && $breed) {
            $historical = $this->forecastService()->breedHistorical($breed);
            $forecasts = $this->forecastsForCalendar(
                fn ($q) => $q->whereNull('cage_id')->where('breed', $breed),
                $calendarDate,
                $horizon,
            );

            return compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate', 'dataSufficiency', 'productionByDate', 'calendarMinYear', 'forecastMinDate')
                + ['forecastDataDays' => $dataSufficiency['current_count']];
        }

        $cage = Cage::where('cage_code', $cageCode)->first();
        $historical = $this->forecastService()->cageHistorical($cageCode);

        $forecasts = $this->forecastsForCalendar(
            fn ($q) => $q->when($cage, fn ($q) => $q->where('cage_id', $cage->id))
                ->when(! $cage, fn ($q) => $q->whereNull('cage_id'))
                ->whereNull('breed'),
            $calendarDate,
            $horizon,
        );

        return compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate', 'dataSufficiency', 'productionByDate', 'calendarMinYear', 'forecastMinDate')
            + ['forecastDataDays' => $dataSufficiency['current_count'], 'breed' => $breed];
    }

    /**
     * Lightweight JSON payload for the Forecast workspace so scope / cage /
     * breed / horizon changes can update the chart + labels in place without
     * re-rendering the whole turbo-frame (no URL change, no history churn).
     */
    public function data(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $scope = $request->get('scope', 'cage');
        $horizon = (int) $request->get('horizon', 7);

        $allCages = $this->forecastService()->recordedCages();
        $allBreeds = $this->forecastService()->recordedBreeds();

        $cageCode = $request->get('cage', $allCages->first() ?? '');
        $breed = $request->get('breed');

        if ($scope === 'breed' && empty($breed)) {
            $breed = $allBreeds->first();
        }

        $dataSufficiency = $this->checkForecastDataSufficiency($scope, $cageCode, $breed);

        $metrics = session('forecast_metrics');
        $recommendedModel = session('recommended_model');
        $showForecast = session('forecast_generated', false);

        // This endpoint is the in-page refresh that follows a scope change, so
        // it has to resolve the same calendar month the frame is showing or the
        // two would disagree on which days carry a prediction.
        $calendarYear = (int) $request->get('year', ReportingDateService::now()->format('Y'));
        $calendarMonth = (int) $request->get('month', ReportingDateService::now()->format('n'));
        $calendarDate = ReportingDateService::now()->copy()->setDate($calendarYear, max(1, min(12, $calendarMonth)), 1);

        $historical = collect();
        $forecasts = collect();

        if ($scope === 'farm') {
            $historical = $this->forecastService()->farmHistorical();
            $forecasts = $this->forecastsForCalendar(
                fn ($q) => $q->whereNull('cage_id')->whereNull('breed'),
                $calendarDate,
                $horizon,
            );
        } elseif ($scope === 'breed' && $breed) {
            $historical = $this->forecastService()->breedHistorical($breed);
            $forecasts = $this->forecastsForCalendar(
                fn ($q) => $q->whereNull('cage_id')->where('breed', $breed),
                $calendarDate,
                $horizon,
            );
        } else {
            $cage = Cage::where('cage_code', $cageCode)->first();
            $forecasts = $this->forecastsForCalendar(
                fn ($q) => $q->when($cage, fn ($q) => $q->where('cage_id', $cage->id))
                    ->when(! $cage, fn ($q) => $q->whereNull('cage_id'))
                    ->whereNull('breed'),
                $calendarDate,
                $horizon,
            );
        }

        $scopeLabel = match ($scope) {
            'farm' => 'Whole Farm',
            'breed' => $breed ?? '',
            default => $cageCode,
        };
        $cageColorMap = Cage::getColorMap();
        $cageColor = $scope === 'farm' ? '#102A4C' : ($cageColorMap[$cageCode] ?? '#6B7280');
        $chartTitle = $showForecast ? 'HISTORICAL DATA VS FORECASTED EGG COUNT' : 'HISTORICAL EGG COUNT';

        return response()->json([
            'scope' => $scope,
            'cageCode' => $cageCode,
            'breed' => $breed,
            'horizon' => $horizon,
            'scopeLabel' => $scopeLabel,
            'cageColor' => $cageColor,
            'chartTitle' => $chartTitle,
            'showForecast' => $showForecast,
            'hasEnoughData' => $dataSufficiency['has_enough'],
            'forecastDataDays' => $dataSufficiency['current_count'],
            'daysRemaining' => $dataSufficiency['days_remaining'],
            'perCage' => $dataSufficiency['per_cage'],
            'historical' => $historical->map(fn ($l) => [
                'date' => is_object($l->log_date) ? $l->log_date->format('Y-m-d') : $l->log_date,
                'egg_count' => $l->egg_count,
            ])->values(),
            'forecasts' => $forecasts->map(fn ($f) => [
                'date' => is_object($f->target_date) ? $f->target_date->format('Y-m-d') : $f->target_date,
                'egg_count' => (int) $f->predicted_egg_count,
            ])->values(),
            'metrics' => $metrics,
            'recommendedModel' => $recommendedModel,
        ]);
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $data = $this->resolveExportData($request);
        if (! $data) {
            return $this->noForecastToExport();
        }

        ['scope' => $scope, 'cageCode' => $cageCode, 'breed' => $breed, 'horizon' => $horizon, 'forecasts' => $forecasts] = $data;

        $filename = 'forecast-'.$scope.'-'.ReportingDateService::reportingDateString().'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($forecasts, $scope, $cageCode, $breed) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['target_date', 'predicted_egg_count', 'scope', 'cage_code', 'breed']);
            foreach ($forecasts as $f) {
                fputcsv($handle, [
                    $f->target_date,
                    $f->predicted_egg_count ?? 0,
                    $scope,
                    $cageCode ?? '',
                    $breed ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportExcel(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $data = $this->resolveExportData($request);
        if (! $data) {
            return $this->noForecastToExport();
        }

        ['forecasts' => $forecasts, 'scope' => $scope, 'cageCode' => $cageCode, 'breed' => $breed, 'horizon' => $horizon] = $data;

        $imagePath = null;
        $payload = $request->isMethod('POST') ? $request->json()->all() : $request->all();
        $rawImage = $payload['chart_image'] ?? null;
        if ($rawImage && preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $rawImage)
            && strlen(base64_decode(explode(',', $rawImage, 2)[1], true)) <= 5 * 1024 * 1024) {
            $imagePath = tempnam(sys_get_temp_dir(), 'lre_fc_');
            register_shutdown_function(function () use ($imagePath) {
                file_exists($imagePath) && @unlink($imagePath);
            });
            $decoded = base64_decode(explode(',', $rawImage, 2)[1], true);
            if ($decoded !== false) {
                file_put_contents($imagePath, $decoded);
            } else {
                $imagePath = null;
            }
        }

        return Excel::download(
            new ForecastExport($forecasts, $scope, $cageCode, $breed, $imagePath),
            'forecast-'.$scope.'-'.ReportingDateService::reportingDateString().'.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $data = $this->resolveExportData($request);
        if (! $data) {
            return $this->noForecastToExport();
        }

        ['forecasts' => $forecasts, 'scope' => $scope, 'cageCode' => $cageCode, 'breed' => $breed, 'horizon' => $horizon] = $data;

        $chartImage = null;
        $payload = $request->isMethod('POST') ? $request->json()->all() : $request->all();
        $rawImage = $payload['chart_image'] ?? null;
        if ($rawImage && preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $rawImage)
            && strlen(base64_decode(explode(',', $rawImage, 2)[1], true)) <= 5 * 1024 * 1024) {
            $chartImage = $rawImage;
        }

        try {
            $pdf = Pdf::loadView('forecast.pdf', compact('forecasts', 'scope', 'cageCode', 'breed', 'horizon', 'chartImage'));

            return $pdf->download('forecast-'.$scope.'-'.ReportingDateService::reportingDateString().'.pdf');
        } catch (\Exception $e) {
            Log::warning('PDF export failed with chart image, retrying without: '.$e->getMessage());
            try {
                $pdf = Pdf::loadView('forecast.pdf', compact('forecasts', 'scope', 'cageCode', 'breed', 'horizon') + ['chartImage' => null]);

                return $pdf->download('forecast-'.$scope.'-'.ReportingDateService::reportingDateString().'.pdf');
            } catch (\Exception $e2) {
                Log::error('PDF export failed even without chart image: '.$e2->getMessage());

                return response()->json(['message' => 'PDF export failed. Please try exporting as Excel instead.'], 500);
            }
        }
    }

    public function downloadProductionData(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $records = $this->forecastService()->datasetRows();

        $filename = 'production_data_'.ReportingDateService::reportingDateString().'.xlsx';

        return Excel::download(new ProductionDataExport($records), $filename);
    }

    /**
     * JSON list of the aggregated forecast dataset (built on the fly from the
     * native production tables) for the "View input records status" FAB modal
     * on the forecast page. Read-only; returns recent rows + a summary
     * (count, distinct days, min/max date) so the user can gauge data depth.
     */
    public function inputRecords(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $dataset = $this->forecastService()->datasetRows();

        $rows = $dataset
            ->groupBy(fn ($r) => $r->date)
            ->map(fn ($group) => (object) [
                'date' => $group->first()->date,
                'total_eggs' => (int) $group->sum('egg_count'),
                'record_count' => $group->count(),
            ])
            ->sortByDesc('date')
            ->values();

        $perCage = $dataset
            ->groupBy('cage_code')
            ->map(fn ($group, $cageCode) => [
                'cage_code' => $cageCode,
                'unique_dates' => $group->pluck('date')->unique()->count(),
            ])
            ->sortKeys()
            ->values()
            ->map(fn ($row) => $row + [
                'ready' => $row['unique_dates'] >= 90,
                'days_remaining' => max(0, 90 - $row['unique_dates']),
            ]);

        $distinctDays = $dataset->pluck('date')->unique()->count();
        $minDate = $dataset->min('date');
        $maxDate = $dataset->max('date');

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'total_records' => $dataset->count(),
                'distinct_days' => $distinctDays,
                'min_date' => $minDate,
                'max_date' => $maxDate,
                'per_cage' => $perCage,
            ],
        ]);
    }

    // Export requests are fired via fetch() with default redirect-following —
    // a redirect() response here used to be silently followed to GET /forecast,
    // whose HTML then got downloaded and saved as "forecast-export-pdf-....pdf"
    // (or .xlsx/.csv), which every PDF/spreadsheet viewer then fails to open.
    // fetch() only treats non-2xx as a failure it can detect, so this needs to
    // be a real error status the JS's `!response.ok` check actually catches.
    private function noForecastToExport()
    {
        return response()->json([
            'message' => 'No forecast has been generated yet for today and this scope/cage/breed — click "Generate Forecast" first, then export.',
        ], 422);
    }

    private function resolveExportData(Request $request): ?array
    {
        $scope = $request->input('scope', 'cage');
        $horizon = (int) $request->input('horizon', 7);
        $cageCode = $request->input('cage');
        $breed = $request->input('breed');

        // Exports reflect the forecast that was actually generated and stored
        // for today, so the days follow the selected forecast duration or a
        // customized calendar range — never the currently-selected horizon
        // radio (which could be different from the run that created the rows).
        //
        // Deliberately stays reporting-date-scoped, unlike the calendar. An
        // export is a snapshot of the current forecast; folding a month-old
        // backtest into it would contradict the "generated today" contract
        // above and inflate the row count and "Horizon: N days" label.
        $historical = collect();
        if ($scope === 'farm') {
            $historical = $this->forecastService()->farmHistorical();
            $forecasts = Forecast::live()
                ->where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->orderBy('target_date')->get();
        } elseif ($scope === 'breed' && $breed) {
            $historical = $this->forecastService()->breedHistorical($breed);
            $forecasts = Forecast::live()
                ->where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->orderBy('target_date')->get();
        } else {
            $cage = $cageCode ? Cage::where('cage_code', $cageCode)->first() : null;
            $historical = $this->forecastService()->cageHistorical($cageCode ?? '');
            $forecasts = Forecast::live()
                ->where('forecast_date', ReportingDateService::reportingDateString())
                ->when($cage, fn ($q) => $q->where('cage_id', $cage->id))
                ->when(! $cage, fn ($q) => $q->whereNull('cage_id'))
                ->whereNull('breed')
                ->whereNotNull('target_date')
                ->orderBy('target_date')->get();
        }

        if ($forecasts->isEmpty()) {
            return null;
        }

        // Report metadata (e.g. the PDF "Horizon: N days" label and the number
        // of exported rows) should reflect the stored days, not the request's
        // horizon value.
        $horizon = $forecasts->count();

        return compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts');
    }
}
