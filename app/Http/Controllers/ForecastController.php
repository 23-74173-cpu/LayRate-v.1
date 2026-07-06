<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Forecast;
use App\Models\Hen;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $scope     = $request->get('scope', 'cage');
        $cageCode  = $request->get('cage', 'CAGE-A');
        $breed     = $request->get('breed');
        $horizon   = (int) $request->get('horizon', 7);
        $allCages  = Cage::orderBy('cage_code')->get();
        $allBreeds = Hen::distinct()->pluck('breed')->filter()->sort()->values();

        $metrics = session('forecast_metrics');
        $recommendedModel = session('recommended_model');

        $hasEnoughData = $this->hasEnoughForecastData($scope, $cageCode, $breed);

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();
            $forecasts  = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->orderBy('target_date')->limit($horizon)->get();

            return view('forecast', compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData'))
                ->with('label', 'Whole Farm');
        }

        if ($scope === 'breed' && $breed) {
            $historical = $this->breedHistorical($breed);
            $forecasts  = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->orderBy('target_date')->limit($horizon)->get();

            return view('forecast', compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData'))
                ->with('label', $breed);
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        $historical = $this->cageHistorical($cageCode);

        $forecasts = Forecast::where('forecast_date', now()->toDateString())
            ->where('cage_id', $cage->id)->whereNull('breed')
            ->orderBy('target_date')->limit($horizon)->get();

        return view('forecast', compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds', 'hasEnoughData'));
    }

    public function downloadTemplate()
    {
        try {
            $pythonBinary = $this->resolvePythonBinary();
            $scriptPath = base_path('forecast-api/generate_forecast_sheet.py');
            $outputName = 'forecast_input_' . now()->format('Ymd_His') . '.xlsx';
            $outputPath = base_path('forecast-api/' . $outputName);

            if (!file_exists($scriptPath)) {
                throw new RuntimeException('Forecast sheet generator not found at: ' . $scriptPath);
            }

            $command = [
                $pythonBinary,
                $scriptPath,
                '--days', '90',
                '--output', $outputName,
            ];

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
        $cageCode  = $request->get('cage', 'CAGE-A');
        $breed     = $request->get('breed');
        $horizon   = (int) $request->get('horizon', 7);

        if (!$this->hasEnoughForecastData($scope, $cageCode, $breed)) {
            return redirect()->back()
                ->with('error', 'The forecast input table must contain at least 90 days of production records before generating a forecast.')
                ->withInput();
        }

        try {
            if ($scope === 'farm') {
                $historical = $this->farmHistorical();

                Forecast::whereNull('cage_id')->whereNull('breed')
                    ->where('forecast_date', now()->toDateString())->delete();

                $result = $this->generateForecast(null, null, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'farm', 'horizon' => $horizon])
                ->with('success', 'Whole-farm forecast generated.')
                ->with('forecast_generated', true)
                ->with('forecast_metrics', $result['metrics'])
                ->with('recommended_model', $result['recommended_model']);
            }

            if ($scope === 'breed' && $breed) {
                $historical = $this->breedHistorical($breed);

                Forecast::whereNull('cage_id')->where('breed', $breed)
                    ->where('forecast_date', now()->toDateString())->delete();

                $result = $this->generateForecast(null, $breed, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'breed', 'breed' => $breed, 'horizon' => $horizon])
                ->with('success', "{$breed} forecast generated.")
                ->with('forecast_generated', true)
                ->with('forecast_metrics', $result['metrics'])
                ->with('recommended_model', $result['recommended_model']);
            }

            $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

            $historical = $this->cageHistorical($cageCode);

            Forecast::where('cage_id', $cage->id)->whereNull('breed')
                ->where('forecast_date', now()->toDateString())->delete();

            $result = $this->generateForecast($cage, null, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon])
                ->with('success', 'Forecast generated.')
                ->with('forecast_generated', true)
                ->with('forecast_metrics', $result['metrics'])
                ->with('recommended_model', $result['recommended_model']);
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

    private function farmHistorical(): Collection
    {
        return DB::table('forecast_input_records')
            ->select('date', DB::raw('SUM(egg_count) as egg_count'), DB::raw('SUM(hen_count) as hen_count'))
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
    private function generateForecast(?Cage $cage, ?string $breed, Collection $historical, int $horizon, bool $save = false): array
    {
        $result = $this->executePythonForecast($cage, $breed, $horizon, $this->collectManualParams(request()));

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
    private function executePythonForecast(?Cage $cage, ?string $breed, int $horizon, array $manualParams = []): array
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
            '--cage', $cage?->cage_code ?? 'ALL',
            '--breed', $breed ?? 'ALL',
            '--horizon', (string) $horizon,
        ];

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
    private function hasEnoughForecastData(string $scope, ?string $cageCode = null, ?string $breed = null): bool
    {
        $query = DB::table('forecast_input_records')
            ->whereNotNull('date')
            ->whereNotNull('cage_code')
            ->whereRaw("TRIM(cage_code) != ''");

        if ($scope === 'cage' && $cageCode) {
            return $query->where('cage_code', $cageCode)->count() >= 90;
        }

        if ($scope === 'breed' && $breed) {
            return $query->where('breed', $breed)->count() >= 90;
        }

        return $query->distinct()->count('date') >= 90;
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

        // Look for a project-level virtual environment.
        $candidates = [
            base_path('forecast-api/.venv/Scripts/python.exe'),
            base_path('forecast-api/.venv/bin/python'),
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
}
