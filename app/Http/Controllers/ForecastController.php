<?php

namespace App\Http\Controllers;

use App\Exports\ForecastExport;
use App\Forecast\ForecastRules;
use App\Jobs\GenerateForecastJob;
use App\Models\Cage;
use App\Models\Forecast;
use App\Models\ForecastRun;
use App\Models\Hen;
use App\Services\ForecastGenerationService;
use App\Services\ReportingDateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ForecastController extends Controller
{
    private function forecastService(): ForecastGenerationService
    {
        return app(ForecastGenerationService::class);
    }

    public function index(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $scope     = $request->get('scope', 'cage');
        $horizon   = (int) $request->get('horizon', 7);

        $calendarYear  = (int) $request->get('year', ReportingDateService::now()->year);
        $calendarMonth = (int) $request->get('month', ReportingDateService::now()->month);
        $calendarDate  = ReportingDateService::now()->copy()->setDate($calendarYear, max(1, min(12, $calendarMonth)), 1);

        $allCages  = DB::table('forecast_input_records')
            ->whereNotNull('cage_code')
            ->whereRaw("TRIM(cage_code) != ''")
            ->whereNotIn('cage_code', ['C01', 'C03'])
            ->distinct()
            ->pluck('cage_code')
            ->filter()
            ->sort()
            ->values();
        $allBreeds = DB::table('forecast_input_records')
            ->whereNotNull('breed')
            ->whereRaw("TRIM(breed) != ''")
            ->distinct()
            ->pluck('breed')
            ->filter()
            ->sort()
            ->values();

        $cageCode = $request->get('cage', $allCages->first() ?? '');
        $breed    = $request->get('breed');

        if ($scope === 'breed' && empty($breed)) {
            $breed = $allBreeds->first();
        }

        $metrics = session('forecast_metrics');
        $recommendedModel = session('recommended_model');

        $dataSufficiency = $this->checkForecastDataSufficiency($scope, $cageCode, $breed);
        $hasEnoughData = $dataSufficiency['has_enough'];

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
            $forecasts  = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();

            $viewData = compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
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
            $forecasts  = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();

            $viewData = compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
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

        $forecasts = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
            ->when($cage, fn($q) => $q->where('cage_id', $cage->id))
            ->when(!$cage, fn($q) => $q->whereNull('cage_id'))
            ->whereNull('breed')
            ->orderBy('target_date')->limit($horizon)->get();

        $viewData = compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
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
            $outputName = 'forecast_input_' . now()->format('Ymd_His') . '.xlsx';
            $outputPath = base_path('forecast-api/' . $outputName);

            if (!file_exists($scriptPath)) {
                throw new RuntimeException('Forecast sheet generator not found at: ' . $scriptPath);
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

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            if (!file_exists($outputPath)) {
                throw new RuntimeException('Forecast sheet file was not created.');
            }

            return response()->download($outputPath, $outputName)->deleteFileAfterSend(true);
        } catch (ProcessFailedException $e) {
            return redirect()->back()
                ->with('error', 'Forecast sheet generation failed: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    public function generate(Request $request)
    {
        $scope     = $request->get('scope', 'cage');
        $horizon   = (int) $request->get('horizon', 7);
        $breed     = $request->get('breed');

        Log::info('Forecast generate request', [
            'scope' => $scope,
            'cage' => $request->get('cage'),
            'breed' => $breed,
            'horizon' => $horizon,
            'start_date' => $request->input('start_date'),
        ]);

        $cageCode = $request->get('cage', DB::table('forecast_input_records')
            ->whereNotNull('cage_code')
            ->whereRaw("TRIM(cage_code) != ''")
            ->distinct()
            ->orderBy('cage_code')
            ->value('cage_code') ?? '');

        if ($scope === 'breed' && empty($breed)) {
            $breed = DB::table('forecast_input_records')
                ->whereNotNull('breed')
                ->whereRaw("TRIM(breed) != ''")
                ->distinct()
                ->orderBy('breed')
                ->value('breed');
        }

        $startDate = $request->input('start_date');

        if ($startDate) {
            // A calendar/date-specific forecast can span multiple consecutive
            // days (e.g. drag-selecting Aug 5-10). Honor the requested horizon,
            // defaulting to a single day when none is supplied.
            $horizon = max(1, (int) $request->get('horizon', 1));

            try {
                $parsed = \Carbon\Carbon::parse($startDate);
                if ($parsed->lt(ForecastRules::minStartDate())) {
                    return redirect()->back()
                        ->with('error', 'Forecast date must be at least tomorrow.')
                        ->withInput();
                }
                if ($parsed->gt(ForecastRules::maxStartDate())) {
                    return redirect()->back()
                        ->with('error', 'Forecast date cannot exceed 30 days from today.')
                        ->withInput();
                }

                $rangeEnd = $parsed->copy()->addDays($horizon - 1)->endOfDay();
                if ($rangeEnd->gt(ForecastRules::maxStartDate())) {
                    return redirect()->back()
                        ->with('error', 'Forecast range cannot extend beyond 30 days from today.')
                        ->withInput();
                }
            } catch (\Exception $e) {
                return redirect()->back()
                    ->with('error', 'Invalid forecast date.')
                    ->withInput();
            }
        }

        $dataSufficiency = $this->checkForecastDataSufficiency($scope, $cageCode, $breed);
        if (!$dataSufficiency['has_enough']) {
            return redirect()->back()
                ->with('error', 'The forecast input table must contain at least 90 days of production records before generating a forecast.')
                ->withInput();
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
                ? ['scope' => 'farm', 'horizon' => $horizon, 'start_date' => $startDate, 'month' => \Carbon\Carbon::parse($startDate)->month, 'year' => \Carbon\Carbon::parse($startDate)->year]
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
                ? ['scope' => 'breed', 'breed' => $breed, 'horizon' => $horizon, 'start_date' => $startDate, 'month' => \Carbon\Carbon::parse($startDate)->month, 'year' => \Carbon\Carbon::parse($startDate)->year]
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
            ? ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon, 'start_date' => $startDate, 'month' => \Carbon\Carbon::parse($startDate)->month, 'year' => \Carbon\Carbon::parse($startDate)->year]
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
     */
    public function status(ForecastRun $forecastRun)
    {
        return response()->json([
            'status' => $forecastRun->status,
            'error_message' => $forecastRun->error_message,
            'metrics' => $forecastRun->result_metrics['metrics'] ?? null,
            'recommended_model' => $forecastRun->result_metrics['recommended_model'] ?? null,
            'redirect_url' => $forecastRun->status === 'completed'
                ? route('forecast', $forecastRun->redirect_params ?? [])
                : null,
        ]);
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

            if (!$fullPath || !file_exists($fullPath)) {
                throw new RuntimeException('Uploaded file not found at: ' . ($fullPath ?: 'unknown path'));
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

            if (!file_exists($scriptPath)) {
                throw new RuntimeException('Forecast import script not found at: ' . $scriptPath);
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

            if (!$process->isSuccessful()) {
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
                    'Import process failed.' . ($detail ? ' ' . $detail : '')
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
            $message = 'Forecast import failed: ' . $e->getMessage();
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

            if (!$fullPath || !file_exists($fullPath)) {
                throw new RuntimeException('Uploaded file not found.');
            }

            // Persist the upload to a temp directory so the confirm step can read it.
            $tempDir = storage_path('app/private/forecast-imports');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0775, true);
            }
            $tempName = 'import_' . bin2hex(random_bytes(16)) . '.xlsx';
            $tempPath = $tempDir . '/' . $tempName;
            $file->move($tempDir, $tempName);

            $pythonBinary = $this->forecastService()->resolvePythonBinary();
            $scriptPath   = base_path('forecast-api/import_forecast_input.py');

            Log::info('Forecast preview starting', [
                'python' => $pythonBinary,
                'script' => $scriptPath,
                'temp_path' => $tempPath,
                'python_exists' => file_exists($pythonBinary),
                'script_exists' => file_exists($scriptPath),
            ]);

            if (!file_exists($scriptPath)) {
                throw new RuntimeException('Forecast import script not found.');
            }

            $command = [$pythonBinary, $scriptPath, $tempPath, '--preview'];
            $process = new Process($command, base_path());
            $process->setTimeout(120);
            $process->setEnv($this->forecastService()->processEnv());
            $process->run();

            if (!$process->isSuccessful()) {
                $errorOutput = trim($process->getErrorOutput());
                $stdOutput   = trim($process->getOutput());
                // Python preview script may emit {"error": "..."} as JSON on failure.
                $detail = $errorOutput;
                if (!$detail && $stdOutput) {
                    $decoded = json_decode($stdOutput, true);
                    $detail = is_array($decoded) && isset($decoded['error']) ? $decoded['error'] : $stdOutput;
                }
                Log::error('Forecast preview process failed', [
                    'exit_code' => $process->getExitCode(),
                    'stderr'    => $errorOutput,
                    'stdout'    => $stdOutput,
                ]);
                throw new RuntimeException('Preview failed. ' . $detail);
            }

            $json = json_decode(trim($process->getOutput()), true);
            if (!is_array($json)) {
                throw new RuntimeException('Invalid preview output from Python script.');
            }

            $json['temp_path']  = $tempPath;
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
                'temp_path'   => ['required', 'string'],
                'source_file' => ['required', 'string'],
            ]);

            $tempPath   = $validated['temp_path'];
            $sourceFile = $validated['source_file'];

            // Security: ensure the path is inside our temp directory.
            $tempDir = realpath(storage_path('app/private/forecast-imports'));
            $realPath = realpath($tempPath);
            if ($tempDir === false || $realPath === false || !str_starts_with($realPath, $tempDir . '/')) {
                throw new RuntimeException('Invalid or expired import session.');
            }

            if (!file_exists($realPath)) {
                throw new RuntimeException('Import file not found. Please re-upload.');
            }

            $pythonBinary = $this->forecastService()->resolvePythonBinary();
            $scriptPath   = base_path('forecast-api/import_forecast_input.py');

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

            if (!$process->isSuccessful()) {
                $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());
                Log::error('Forecast import confirm failed', [
                    'exit_code' => $process->getExitCode(),
                    'stderr'    => $error,
                ]);
                throw new RuntimeException('Import failed. ' . $error);
            }

            $output = trim($process->getOutput());
            $count  = 0;
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
        if (!$request || $request->input('mode') !== 'manual') {
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

        $filled = array_filter($manualFields, fn($v) => $v !== null && $v !== '');
        return count($filled) === count($manualFields) ? $filled : [];
    }


    /**
     * Determine whether the forecast_input_records table has enough historical
     * data for the requested scope.
     *
     * Whole farm needs at least 90 distinct dates. Per-cage / per-breed need
     * at least 90 rows for the selected cage or breed.
     */
    private function checkForecastDataSufficiency(string $scope, ?string $cageCode = null, ?string $breed = null): array
    {
        $fullCount = DB::table('forecast_input_records')->count();
        $query = DB::table('forecast_input_records')
            ->whereNotNull('date')
            ->whereNotNull('cage_code')
            ->whereRaw("TRIM(cage_code) != ''");

        $currentCount = match (true) {
            $scope === 'cage' && $cageCode => (int) $query->where('cage_code', $cageCode)->count(),
            $scope === 'breed' && $breed   => (int) $query->where('breed', $breed)->count(),
            default                        => (int) $query->distinct()->count('date'),
        };

        Log::info('Forecast data sufficiency check', [
            'scope' => $scope,
            'cage_code' => $cageCode,
            'breed' => $breed,
            'current_count' => $currentCount,
            'threshold' => 90,
            'has_enough' => $currentCount >= 90,
            'forecast_input_records_total' => $fullCount,
        ]);

        return [
            'has_enough'    => $currentCount >= 90,
            'current_count' => $currentCount,
        ];
    }





    public function clear(Request $request)
    {
        $scope = $request->get('scope', 'cage');
        $breed = $request->get('breed');
        $cageCode = $request->get('cage', 'ALL');

        $query = Forecast::where('forecast_date', ReportingDateService::reportingDateString());

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

    private function respondAfterGenerate(Request $request, array $redirectParams, string $successMessage, array $result)
    {
        if ($this->wantsTurboStream($request)) {
            $request->query->add($redirectParams);
            $viewData = $this->buildForecastViewData($request);
            $viewData['successMessage'] = $successMessage;
            $viewData['metrics'] = $result['metrics'];
            $viewData['recommendedModel'] = $result['recommended_model'];

            session()->flash('forecast_generated', true);

            return $this->renderTurboStream($viewData);
        }

        return redirect()->route('forecast', $redirectParams)
            ->with('success', $successMessage)
            ->with('forecast_generated', true)
            ->with('forecast_metrics', $result['metrics'])
            ->with('recommended_model', $result['recommended_model']);
    }

    private function wantsTurboStream(Request $request): bool
    {
        $accept = $request->header('Accept', '');
        return str_contains($accept, 'text/vnd.turbo-stream.html');
    }

    private function renderTurboStream(array $viewData): \Illuminate\Http\Response
    {
        $workspaceHtml = view('forecast._workspace', $viewData)->render();
        $calendarHtml  = view('forecast._calendar', $viewData)->render();

        $stream = '';
        $stream .= '<turbo-stream action="replace" target="forecast-workspace"><template>' . $workspaceHtml . '</template></turbo-stream>';
        $stream .= '<turbo-stream action="replace" target="production-calendar"><template>' . $calendarHtml . '</template></turbo-stream>';

        return response($stream)->header('Content-Type', 'text/vnd.turbo-stream.html');
    }

    /**
     * Build the view data array shared by index, clear, and respondAfterGenerate.
     */
    private function buildForecastViewData(Request $request): array
    {
        $scope     = $request->get('scope', 'cage');
        $horizon   = (int) $request->get('horizon', 7);

        $calendarYear  = (int) $request->get('year', ReportingDateService::now()->year);
        $calendarMonth = (int) $request->get('month', ReportingDateService::now()->month);
        $calendarDate  = ReportingDateService::now()->copy()->setDate($calendarYear, max(1, min(12, $calendarMonth)), 1);

        $allCages  = DB::table('forecast_input_records')
            ->whereNotNull('cage_code')
            ->whereRaw("TRIM(cage_code) != ''")
            ->whereNotIn('cage_code', ['C01', 'C03'])
            ->distinct()
            ->pluck('cage_code')
            ->filter()
            ->sort()
            ->values();
        $allBreeds = DB::table('forecast_input_records')
            ->whereNotNull('breed')
            ->whereRaw("TRIM(breed) != ''")
            ->distinct()
            ->pluck('breed')
            ->filter()
            ->sort()
            ->values();

        $cageCode = $request->get('cage', $allCages->first() ?? '');
        $breed    = $request->get('breed');

        if ($scope === 'breed' && empty($breed)) {
            $breed = $allBreeds->first();
        }

        $dataSufficiency = $this->checkForecastDataSufficiency($scope, $cageCode, $breed);
        $hasEnoughData = $dataSufficiency['has_enough'];

        $historical = collect();
        $forecasts = collect();
        $metrics = null;
        $recommendedModel = null;

        if ($scope === 'farm') {
            $historical = $this->forecastService()->farmHistorical();
            $forecasts = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();

            return compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
                + ['forecastDataDays' => $dataSufficiency['current_count'], 'breed' => $breed];
        }

        if ($scope === 'breed' && $breed) {
            $historical = $this->forecastService()->breedHistorical($breed);
            $forecasts = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();

            return compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
                + ['forecastDataDays' => $dataSufficiency['current_count']];
        }

        $cage = Cage::where('cage_code', $cageCode)->first();
        $historical = $this->forecastService()->cageHistorical($cageCode);

        $forecasts = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
            ->when($cage, fn($q) => $q->where('cage_id', $cage->id))
            ->when(!$cage, fn($q) => $q->whereNull('cage_id'))
            ->whereNull('breed')
            ->whereNotNull('target_date')
            ->orderBy('target_date')->limit($horizon)->get();

        return compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
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

        $scope   = $request->get('scope', 'cage');
        $horizon = (int) $request->get('horizon', 7);

        $allCages = DB::table('forecast_input_records')
            ->whereNotNull('cage_code')
            ->whereRaw("TRIM(cage_code) != ''")
            ->whereNotIn('cage_code', ['C01', 'C03'])
            ->distinct()
            ->pluck('cage_code')
            ->filter()
            ->sort()
            ->values();
        $allBreeds = DB::table('forecast_input_records')
            ->whereNotNull('breed')
            ->whereRaw("TRIM(breed) != ''")
            ->distinct()
            ->pluck('breed')
            ->filter()
            ->sort()
            ->values();

        $cageCode = $request->get('cage', $allCages->first() ?? '');
        $breed    = $request->get('breed');

        if ($scope === 'breed' && empty($breed)) {
            $breed = $allBreeds->first();
        }

        $dataSufficiency = $this->checkForecastDataSufficiency($scope, $cageCode, $breed);

        $metrics = session('forecast_metrics');
        $recommendedModel = session('recommended_model');
        $showForecast = session('forecast_generated', false);

        $historical = collect();
        $forecasts  = collect();

        if ($scope === 'farm') {
            $historical = $this->forecastService()->farmHistorical();
            $forecasts  = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();
        } elseif ($scope === 'breed' && $breed) {
            $historical = $this->forecastService()->breedHistorical($breed);
            $forecasts  = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();
        } else {
            $cage = Cage::where('cage_code', $cageCode)->first();
            $historical = $this->forecastService()->cageHistorical($cageCode);
            $forecasts  = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
                ->when($cage, fn($q) => $q->where('cage_id', $cage->id))
                ->when(!$cage, fn($q) => $q->whereNull('cage_id'))
                ->whereNull('breed')
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();
        }

        $scopeLabel = match ($scope) {
            'farm'  => 'Whole Farm',
            'breed' => $breed ?? '',
            default => $cageCode,
        };
        $cageColorMap = Cage::getColorMap();
        $cageColor = $scope === 'farm' ? '#102A4C' : ($cageColorMap[$cageCode] ?? '#6B7280');
        $chartTitle = $showForecast ? 'HISTORICAL DATA VS FORECASTED EGG COUNT' : 'HISTORICAL EGG COUNT';

        return response()->json([
            'scope'            => $scope,
            'cageCode'         => $cageCode,
            'breed'            => $breed,
            'horizon'          => $horizon,
            'scopeLabel'       => $scopeLabel,
            'cageColor'        => $cageColor,
            'chartTitle'       => $chartTitle,
            'showForecast'     => $showForecast,
            'hasEnoughData'    => $dataSufficiency['has_enough'],
            'forecastDataDays' => $dataSufficiency['current_count'],
            'historical'       => $historical->map(fn($l) => [
                'date'      => is_object($l->log_date) ? $l->log_date->format('Y-m-d') : $l->log_date,
                'egg_count' => $l->egg_count,
            ])->values(),
            'forecasts'        => $forecasts->map(fn($f) => [
                'date'      => is_object($f->target_date) ? $f->target_date->format('Y-m-d') : $f->target_date,
                'egg_count' => (int) $f->predicted_egg_count,
            ])->values(),
            'metrics'          => $metrics,
            'recommendedModel' => $recommendedModel,
        ]);
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $data = $this->resolveExportData($request);
        if (!$data) {
            return $this->noForecastToExport();
        }

        ['scope' => $scope, 'cageCode' => $cageCode, 'breed' => $breed, 'horizon' => $horizon, 'forecasts' => $forecasts] = $data;

        $filename = 'forecast-' . $scope . '-' . ReportingDateService::reportingDateString() . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
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
        if (!$data) {
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
            'forecast-' . $scope . '-' . ReportingDateService::reportingDateString() . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $data = $this->resolveExportData($request);
        if (!$data) {
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
            return $pdf->download('forecast-' . $scope . '-' . ReportingDateService::reportingDateString() . '.pdf');
        } catch (\Exception $e) {
            Log::warning('PDF export failed with chart image, retrying without: ' . $e->getMessage());
            try {
                $pdf = Pdf::loadView('forecast.pdf', compact('forecasts', 'scope', 'cageCode', 'breed', 'horizon') + ['chartImage' => null]);
                return $pdf->download('forecast-' . $scope . '-' . ReportingDateService::reportingDateString() . '.pdf');
            } catch (\Exception $e2) {
                Log::error('PDF export failed even without chart image: ' . $e2->getMessage());
                return response()->json(['message' => 'PDF export failed. Please try exporting as Excel instead.'], 500);
            }
        }
    }

    public function downloadProductionData(Request $request)
    {
        if ($redirect = $this->ensureAdminOrRedirect($request)) {
            return $redirect;
        }

        $records = DB::table('forecast_input_records')
            ->orderBy('date')
            ->orderBy('cage_code')
            ->get();

        $filename = 'production_data_' . ReportingDateService::reportingDateString() . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['date', 'cage_code', 'breed', 'flock_age_weeks', 'hen_count', 'egg_count', 'temperature_c', 'humidity_percent', 'crude_protein_percent', 'feed_consumed_kg', 'mortality_count']);
            foreach ($records as $row) {
                fputcsv($handle, [
                    $row->date,
                    $row->cage_code,
                    $row->breed,
                    $row->flock_age_weeks,
                    $row->hen_count,
                    $row->egg_count,
                    $row->temperature_c,
                    $row->humidity_percent,
                    $row->crude_protein_percent,
                    $row->feed_consumed_kg,
                    $row->mortality_count,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
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
        $scope   = $request->input('scope', 'cage');
        $horizon = (int) $request->input('horizon', 7);
        $cageCode = $request->input('cage');
        $breed    = $request->input('breed');

        $historical = collect();
        if ($scope === 'farm') {
            $historical = $this->forecastService()->farmHistorical();
            $forecasts = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->orderBy('target_date')->limit($horizon)->get();
        } elseif ($scope === 'breed' && $breed) {
            $historical = $this->forecastService()->breedHistorical($breed);
            $forecasts = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->orderBy('target_date')->limit($horizon)->get();
        } else {
            $cage = $cageCode ? Cage::where('cage_code', $cageCode)->first() : null;
            $historical = $this->forecastService()->cageHistorical($cageCode ?? '');
        $forecasts = Forecast::where('forecast_date', ReportingDateService::reportingDateString())
            ->when($cage, fn($q) => $q->where('cage_id', $cage->id))
            ->when(!$cage, fn($q) => $q->whereNull('cage_id'))
            ->whereNull('breed')
            ->whereNotNull('target_date')
            ->orderBy('target_date')->limit($horizon)->get();
        }

        if ($forecasts->isEmpty()) {
            return null;
        }

        return compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts');
    }
}
