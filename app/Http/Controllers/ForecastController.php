<?php

namespace App\Http\Controllers;

use App\Exports\ForecastExport;
use App\Models\Cage;
use App\Models\Forecast;
use App\Models\Hen;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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
    public function index(Request $request)
    {
        $scope     = $request->get('scope', 'cage');
        $horizon   = (int) $request->get('horizon', 7);

        $calendarYear  = (int) $request->get('year', now()->year);
        $calendarMonth = (int) $request->get('month', now()->month);
        $calendarDate  = now()->setDate($calendarYear, max(1, min(12, $calendarMonth)), 1);

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
            $historical = $this->farmHistorical();
            $forecasts  = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();

            $viewData = compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
                + ['forecastDataDays' => $dataSufficiency['current_count']];

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
            $historical = $this->breedHistorical($breed);
            $forecasts  = Forecast::where('forecast_date', now()->toDateString())
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

        $historical = $this->cageHistorical($cageCode);

        $forecasts = Forecast::where('forecast_date', now()->toDateString())
            ->when($cage, fn($q) => $q->where('cage_id', $cage->id))
            ->when(!$cage, fn($q) => $q->whereNull('cage_id'))
            ->whereNull('breed')
            ->orderBy('target_date')->limit($horizon)->get();

        $viewData = compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
            + ['forecastDataDays' => $dataSufficiency['current_count']];

        if ($request->header('Turbo-Frame') === 'production-calendar') {
            return view('forecast._calendar', $viewData);
        }

        if ($request->header('Turbo-Frame') === 'forecast-workspace') {
            return view('forecast._workspace', $viewData);
        }

        return view('forecast', $viewData);
    }

    public function downloadTemplate(Request $request)
    {
        try {
            $pythonBinary = $this->resolvePythonBinary();
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
            $process->setEnv($this->processEnv());
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
            $horizon = 1;

            try {
                $parsed = \Carbon\Carbon::parse($startDate);
                $maxDate = now()->addDays(30);
                if ($parsed->lt(now()->addDay()->startOfDay())) {
                    return redirect()->back()
                        ->with('error', 'Forecast date must be at least tomorrow.')
                        ->withInput();
                }
                if ($parsed->gt($maxDate->endOfDay())) {
                    return redirect()->back()
                        ->with('error', 'Forecast date cannot exceed 30 days from today.')
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

        try {
            if ($scope === 'farm') {
                $historical = $this->farmHistorical();

                if (!$startDate) {
                    Forecast::whereNull('cage_id')->whereNull('breed')
                        ->where('forecast_date', now()->toDateString())->delete();
                }

                $result = $this->generateForecast(null, 'ALL', null, $historical, $horizon, true, $startDate);

                $successMessage = $startDate ? 'Single-day whole-farm forecast generated.' : 'Whole-farm forecast generated.';
                $redirectParams = array_merge(
                    ['scope' => 'farm', 'horizon' => $horizon],
                    $startDate ? ['start_date' => $startDate] : []
                );

                return $this->respondAfterGenerate($request, $redirectParams, $successMessage, $result);
            }

            if ($scope === 'breed' && $breed) {
                $historical = $this->breedHistorical($breed);

                if (!$startDate) {
                    Forecast::whereNull('cage_id')->where('breed', $breed)
                        ->where('forecast_date', now()->toDateString())->delete();
                }

                $result = $this->generateForecast(null, 'ALL', $breed, $historical, $horizon, true, $startDate);

                $successMessage = $startDate ? "Single-day {$breed} forecast generated." : "{$breed} forecast generated.";
                $redirectParams = array_merge(
                    ['scope' => 'breed', 'breed' => $breed, 'horizon' => $horizon],
                    $startDate ? ['start_date' => $startDate] : []
                );

                return $this->respondAfterGenerate($request, $redirectParams, $successMessage, $result);
            }

            $cage = Cage::where('cage_code', $cageCode)->first();

            $historical = $this->cageHistorical($cageCode);

            if (!$startDate) {
                $forecastQuery = Forecast::whereNull('breed')
                    ->where('forecast_date', now()->toDateString());
                if ($cage) {
                    $forecastQuery->where('cage_id', $cage->id)->delete();
                } else {
                    $forecastQuery->whereNull('cage_id')->delete();
                }
            }

            $result = $this->generateForecast($cage, $cageCode, null, $historical, $horizon, true, $startDate);

            $successMessage = $startDate ? 'Single-day forecast generated.' : 'Forecast generated.';
            $redirectParams = array_merge(
                ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon],
                $startDate ? ['start_date' => $startDate] : []
            );

            return $this->respondAfterGenerate($request, $redirectParams, $successMessage, $result);
        } catch (ProcessFailedException $e) {
            return redirect()->back()
                ->with('error', 'Forecast process failed: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
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

            $pythonBinary = $this->resolvePythonBinary();
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
            $process->setEnv($this->processEnv());
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

            $pythonBinary = $this->resolvePythonBinary();
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
            $process->setEnv($this->processEnv());
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

            $pythonBinary = $this->resolvePythonBinary();
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
            $process->setEnv($this->processEnv());
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

    private function farmHistorical(int $days = 14): Collection
    {
        return DB::table('forecast_input_records')
            ->select('date', DB::raw('SUM(egg_count) as egg_count'), DB::raw('SUM(hen_count) as hen_count'))
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit($days)
            ->get()
            ->map(fn($row) => tap((object) [
                'log_date'  => $row->date,
                'egg_count' => (int) $row->egg_count,
                'hen_count' => (int) $row->hen_count,
                'hdep'      => $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0,
            ], fn($r) => $r))
            ->reverse()
            ->values();
    }

    private function breedHistorical(string $breed): Collection
    {
        return DB::table('forecast_input_records')
            ->select('date', DB::raw('SUM(egg_count) as egg_count'), DB::raw('SUM(hen_count) as hen_count'))
            ->where('breed', $breed)
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(14)
            ->get()
            ->map(fn($row) => tap((object) [
                'log_date'  => $row->date,
                'egg_count' => (int) $row->egg_count,
                'hen_count' => (int) $row->hen_count,
                'hdep'      => $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0,
            ], fn($r) => $r))
            ->reverse()
            ->values();
    }

    private function cageHistorical(string $cageCode): Collection
    {
        return DB::table('forecast_input_records')
            ->select('date', DB::raw('SUM(egg_count) as egg_count'), DB::raw('SUM(hen_count) as hen_count'))
            ->where('cage_code', $cageCode)
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(14)
            ->get()
            ->map(fn($row) => tap((object) [
                'log_date'  => $row->date,
                'egg_count' => (int) $row->egg_count,
                'hen_count' => (int) $row->hen_count,
                'hdep'      => $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0,
            ], fn($r) => $r))
            ->reverse()
            ->values();
    }

    /**
     * Execute the Python forecasting pipeline and optionally persist results.
     *
     * @param Cage|null $cage
     * @param string|null $breed
     * @param Collection $historical Kept for signature/backward compatibility; Python loads its own data.
     * @param int $horizon
     * @param bool $save
     * @return array{forecasts: Collection, metrics: array, recommended_model: string}
     */
    private function generateForecast(?Cage $cage, string $cageCode, ?string $breed, Collection $historical, int $horizon, bool $save = false, ?string $startDate = null): array
    {
        $result = $this->executePythonForecast($cageCode, $breed, $horizon, $this->collectManualParams(request()), $startDate);

        Log::info('Forecast generation result', [
            'recommended_model' => $result['recommended_model'] ?? null,
            'metrics' => $result['metrics'] ?? [],
            'forecast_values' => array_slice(array_map(fn($f) => [
                'date' => $f['date'] ?? null,
                'predicted_egg_count' => $f['predicted_egg_count'] ?? null,
            ], $result['forecast'] ?? []), 0, 5),
        ]);

        $forecasts = $save
            ? $this->persistForecasts($result, $cage, $breed)
            : $this->buildForecastCollection($result, $cage, $breed);

        return [
            'forecasts'         => $forecasts,
            'metrics'           => $result['metrics'] ?? [],
            'recommended_model' => $result['recommended_model'] ?? 'Unknown',
        ];
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
     * Execute the Python forecast runner via Symfony Process.
     */
    private function executePythonForecast(string $cageCode, ?string $breed, int $horizon, array $manualParams = [], ?string $startDate = null): array
    {
        $pythonBinary = $this->resolvePythonBinary();
        $runnerPath = base_path('forecast-api/forecast_runner.py');

        if (!file_exists($runnerPath)) {
            throw new RuntimeException('Forecast runner not found at: ' . $runnerPath);
        }

        $command = [
            $pythonBinary,
            $runnerPath,
            '--mode', empty($manualParams) ? 'auto' : 'manual',
            '--cage', $cageCode,
            '--breed', $breed ?? 'ALL',
            '--horizon', (string) $horizon,
        ];

        if ($startDate) {
            $command[] = '--start-date';
            $command[] = $startDate;
        }

        foreach ($manualParams as $key => $value) {
            $command[] = '--' . str_replace('_', '-', $key);
            $command[] = (string) $value;
        }

        $process = new Process($command, base_path());
        $process->setTimeout(300);
        $process->setEnv($this->processEnv());

        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $stdOutput = trim($process->getOutput());
            $pythonError = $errorOutput ?: $stdOutput;

            if (str_contains($pythonError, 'No module named')) {
                throw new RuntimeException(
                    'Forecast Python environment is missing required packages. ' .
                    'Install dependencies with: pip install -r forecast-api/requirements.txt ' .
                    "(using Python binary: {$pythonBinary})."
                );
            }

            throw new ProcessFailedException($process);
        }

        $output = trim($process->getOutput());

        Log::info('Forecast runner output', [
            'command' => $command,
            'scope' => request()->get('scope', 'cage'),
            'cage' => request()->get('cage'),
            'breed' => request()->get('breed'),
            'horizon' => $horizon,
            'stdout' => $output,
        ]);

        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON from forecast runner: ' . $output);
        }

        if (($data['success'] ?? false) !== true) {
            throw new RuntimeException($data['error'] ?? 'Unknown forecast runner error.');
        }

        return $data;
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

    /**
     * Resolve the Python interpreter to use for the forecast runner.
     *
     * Honors FORECAST_PYTHON_BINARY / services.forecast.python_binary first.
     * If that value is a bare command (not an absolute path) and a project
     * virtual environment exists, prefer the venv interpreter.
     */
    private function resolvePythonBinary(): string
    {
        $configured = config(
            'services.forecast.python_binary',
            env('FORECAST_PYTHON_BINARY', 'python')
        );

        // If an absolute or relative file path was configured and exists, use it.
        if (str_contains($configured, DIRECTORY_SEPARATOR) && file_exists($configured)) {
            return $configured;
        }

        // Look for a project-level virtual environment (both .venv/ and venv/).
        $candidates = [
            base_path('forecast-api/.venv/Scripts/python.exe'),
            base_path('forecast-api/.venv/bin/python'),
            base_path('forecast-api/venv/Scripts/python.exe'),
            base_path('forecast-api/venv/bin/python'),
            base_path('.venv/Scripts/python.exe'),
            base_path('.venv/bin/python'),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return $configured;
    }

    /**
     * Environment variables passed to the Python process.
     *
     * Includes DB credentials and preserves Windows system variables required
     * for loading socket/asyncio extensions.
     */
    private function processEnv(): array
    {
        $env = [
            'DB_HOST'     => config('database.connections.mysql.host', '127.0.0.1'),
            'DB_PORT'     => (string) config('database.connections.mysql.port', 3306),
            'DB_DATABASE' => config('database.connections.mysql.database', 'layrate'),
            'DB_USERNAME' => config('database.connections.mysql.username', 'root'),
            'DB_PASSWORD' => config('database.connections.mysql.password', ''),
        ];

        // Windows requires these to initialize Winsock / asyncio.
        foreach (['SYSTEMROOT', 'SYSTEMDRIVE', 'WINDIR', 'PATH', 'USERPROFILE', 'TEMP', 'TMP'] as $key) {
            $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key) ?? null;
            if ($value !== null && $value !== '') {
                $env[$key] = $value;
            }
        }

        return $env;
    }

    /**
     * Persist forecast rows returned by the Python runner.
     */
    private function persistForecasts(array $result, ?Cage $cage, ?string $breed): Collection
    {
        $collection = $this->buildForecastCollection($result, $cage, $breed);

        foreach ($collection as $forecast) {
            $forecast->save();
        }

        return $collection;
    }

    /**
     * Build an in-memory collection of Forecast models from runner output.
     */
    private function buildForecastCollection(array $result, ?Cage $cage, ?string $breed): Collection
    {
        $forecasts = collect();
        $today = now()->toDateString();

        foreach ($result['forecast'] ?? [] as $item) {
            $forecasts->push(new Forecast([
                'cage_id'             => $cage?->id,
                'breed'               => $breed,
                'forecast_date'       => $today,
                'target_date'         => $item['date'],
                'predicted_egg_count' => $item['predicted_egg_count'],
            ]));
        }

        return $forecasts;
    }

    public function clear(Request $request)
    {
        $scope = $request->get('scope', 'cage');
        $breed = $request->get('breed');
        $cageCode = $request->get('cage', 'ALL');

        $query = Forecast::where('forecast_date', now()->toDateString());

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

        $calendarYear  = (int) $request->get('year', now()->year);
        $calendarMonth = (int) $request->get('month', now()->month);
        $calendarDate  = now()->setDate($calendarYear, max(1, min(12, $calendarMonth)), 1);

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
            $historical = $this->farmHistorical();
            $forecasts = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();

            return compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
                + ['forecastDataDays' => $dataSufficiency['current_count']];
        }

        if ($scope === 'breed' && $breed) {
            $historical = $this->breedHistorical($breed);
            $forecasts = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->whereNotNull('target_date')
                ->orderBy('target_date')->limit($horizon)->get();

            return compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
                + ['forecastDataDays' => $dataSufficiency['current_count']];
        }

        $cage = Cage::where('cage_code', $cageCode)->first();
        $historical = $this->cageHistorical($cageCode);

        $forecasts = Forecast::where('forecast_date', now()->toDateString())
            ->when($cage, fn($q) => $q->where('cage_id', $cage->id))
            ->when(!$cage, fn($q) => $q->whereNull('cage_id'))
            ->whereNull('breed')
            ->whereNotNull('target_date')
            ->orderBy('target_date')->limit($horizon)->get();

        return compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData', 'calendarDate')
            + ['forecastDataDays' => $dataSufficiency['current_count']];
    }

    public function exportCsv(Request $request)
    {
        $data = $this->resolveExportData($request);
        if (!$data) {
            return $this->noForecastToExport();
        }

        ['scope' => $scope, 'cageCode' => $cageCode, 'breed' => $breed, 'horizon' => $horizon, 'forecasts' => $forecasts] = $data;

        $filename = 'forecast-' . $scope . '-' . now()->format('Y-m-d') . '.csv';
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
                    $f->predicted_egg_count ?? $f->predicted_hdep ?? 0,
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
            'forecast-' . $scope . '-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
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
            return $pdf->download('forecast-' . $scope . '-' . now()->format('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            Log::warning('PDF export failed with chart image, retrying without: ' . $e->getMessage());
            try {
                $pdf = Pdf::loadView('forecast.pdf', compact('forecasts', 'scope', 'cageCode', 'breed', 'horizon') + ['chartImage' => null]);
                return $pdf->download('forecast-' . $scope . '-' . now()->format('Y-m-d') . '.pdf');
            } catch (\Exception $e2) {
                Log::error('PDF export failed even without chart image: ' . $e2->getMessage());
                return response()->json(['message' => 'PDF export failed. Please try exporting as Excel instead.'], 500);
            }
        }
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
            $historical = $this->farmHistorical();
            $forecasts = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->orderBy('target_date')->limit($horizon)->get();
        } elseif ($scope === 'breed' && $breed) {
            $historical = $this->breedHistorical($breed);
            $forecasts = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->orderBy('target_date')->limit($horizon)->get();
        } else {
            $cage = $cageCode ? Cage::where('cage_code', $cageCode)->first() : null;
            $historical = $this->cageHistorical($cageCode ?? '');
        $forecasts = Forecast::where('forecast_date', now()->toDateString())
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
