<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Forecast;
use App\Models\Hen;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        if ($scope === 'farm') {
            $historical = $this->farmHistorical();
            $forecasts  = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->whereNull('breed')
                ->orderBy('target_date')->limit($horizon)->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $result = $this->generateForecast(null, null, $historical, $horizon);
                $forecasts = $result['forecasts'];
                $metrics = $metrics ?: $result['metrics'];
                $recommendedModel = $recommendedModel ?: $result['recommended_model'];
            }

            return view('forecast', compact('scope', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds'))
                ->with('label', 'Whole Farm');
        }

        if ($scope === 'breed' && $breed) {
            $historical = $this->breedHistorical($breed);
            $forecasts  = Forecast::where('forecast_date', now()->toDateString())
                ->whereNull('cage_id')->where('breed', $breed)
                ->orderBy('target_date')->limit($horizon)->get();

            if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
                $result = $this->generateForecast(null, $breed, $historical, $horizon);
                $forecasts = $result['forecasts'];
                $metrics = $metrics ?: $result['metrics'];
                $recommendedModel = $recommendedModel ?: $result['recommended_model'];
            }

            return view('forecast', compact('scope', 'cageCode', 'breed', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds'))
                ->with('label', $breed);
        }

        $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

        $historical = $cage->productionLogs()
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        $forecasts = Forecast::where('forecast_date', now()->toDateString())
            ->where('cage_id', $cage->id)->whereNull('breed')
            ->orderBy('target_date')->limit($horizon)->get();

        if ($forecasts->isEmpty() && $historical->isNotEmpty()) {
            $result = $this->generateForecast($cage, null, $historical, $horizon);
            $forecasts = $result['forecasts'];
            $metrics = $metrics ?: $result['metrics'];
            $recommendedModel = $recommendedModel ?: $result['recommended_model'];
        }

        return view('forecast', compact('scope', 'cage', 'cageCode', 'horizon', 'historical', 'forecasts', 'metrics', 'recommendedModel', 'allCages', 'allBreeds'));
    }

    public function downloadTemplate()
    {
        try {
            $pythonBinary = $this->resolvePythonBinary();
            $scriptPath = base_path('forecast-api/create_empty_forecast_template.py');
            $outputPath = base_path('forecast-api/forecast_template.xlsx');

            if (!file_exists($scriptPath)) {
                throw new RuntimeException('Template generator not found at: ' . $scriptPath);
            }

            $process = new Process([$pythonBinary, $scriptPath], base_path('forecast-api'));
            $process->setTimeout(60);
            $process->setEnv($this->processEnv());
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            if (!file_exists($outputPath)) {
                throw new RuntimeException('Template file was not created.');
            }

            return response()->download($outputPath, 'forecast_template.xlsx')->deleteFileAfterSend(true);
        } catch (ProcessFailedException $e) {
            return redirect()->back()
                ->with('error', 'Template generation failed: ' . $e->getMessage());
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

        try {
            if ($scope === 'farm') {
                $historical = $this->farmHistorical();

                Forecast::whereNull('cage_id')->whereNull('breed')
                    ->where('forecast_date', now()->toDateString())->delete();

                $result = $this->generateForecast(null, null, $historical, $horizon, true);

                return redirect()->route('forecast', ['scope' => 'farm', 'horizon' => $horizon])
                    ->with('success', 'Whole-farm forecast generated.')
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
                    ->with('forecast_metrics', $result['metrics'])
                    ->with('recommended_model', $result['recommended_model']);
            }

            $cage = Cage::where('cage_code', $cageCode)->firstOrFail();

            $historical = $cage->productionLogs()
                ->orderByDesc('log_date')
                ->limit(14)
                ->get()
                ->reverse()
                ->values();

            Forecast::where('cage_id', $cage->id)->whereNull('breed')
                ->where('forecast_date', now()->toDateString())->delete();

            $result = $this->generateForecast($cage, null, $historical, $horizon, true);

            return redirect()->route('forecast', ['scope' => 'cage', 'cage' => $cageCode, 'horizon' => $horizon])
                ->with('success', 'Forecast generated.')
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

    private function farmHistorical(): Collection
    {
        return ProductionLog::selectRaw('log_date, SUM(egg_count) as egg_count, SUM(hen_count) as hen_count')
            ->groupBy('log_date')
            ->orderByDesc('log_date')
            ->limit(14)
            ->get()
            ->map(fn($row) => tap(clone $row, fn($r) => $r->hdep = $r->hen_count > 0 ? round(($r->egg_count / $r->hen_count) * 100, 2) : 0))
            ->reverse()
            ->values();
    }

    private function breedHistorical(string $breed): Collection
    {
        return ProductionLog::selectRaw('production_logs.log_date, SUM(production_logs.egg_count) as egg_count, SUM(production_logs.hen_count) as hen_count')
            ->join('cage_slots', 'cage_slots.id', '=', 'production_logs.cage_slot_id')
            ->join('hens', 'hens.cage_slot_id', '=', 'cage_slots.id')
            ->whereRaw('hens.id = (SELECT id FROM hens h2 WHERE h2.cage_slot_id = cage_slots.id AND h2.is_active = 1 LIMIT 1)')
            ->where('hens.breed', $breed)
            ->groupBy('production_logs.log_date')
            ->orderByDesc('production_logs.log_date')
            ->limit(14)
            ->get()
            ->map(fn($row) => tap(clone $row, fn($r) => $r->hdep = $r->hen_count > 0 ? round(($r->egg_count / $r->hen_count) * 100, 2) : 0))
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
