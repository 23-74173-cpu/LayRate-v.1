<?php

namespace App\Services;

use App\Models\Cage;
use App\Models\Forecast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Forecast generation orchestration shared by the web controller and the
 * GenerateForecastJob queue worker. No HTTP/controller dependencies — all
 * data arrives via method parameters, so the same code path runs identically
 * in a request and a worker. python-binary / process-env resolution is also
 * exposed here (public) as the single source used by the controller's other
 * Python flows (download-template/import/import-preview/import-confirm).
 */
class ForecastGenerationService
{
    public function farmHistorical(int $days = 14): Collection
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

    public function breedHistorical(string $breed): Collection
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

    public function cageHistorical(string $cageCode): Collection
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
     *
     * $manualParams are captured from the real HTTP request up front and passed
     * in — a queue worker has no matching request, so they can never be read
     * from request() here.
     */
    public function generateForecast(?Cage $cage, string $cageCode, ?string $breed, Collection $historical, int $horizon, bool $save = false, ?string $startDate = null, array $manualParams = []): array
    {
        $result = $this->executePythonForecast($cageCode, $breed, $horizon, $manualParams, $startDate);

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
            'cage' => $cageCode,
            'breed' => $breed,
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
     * Resolve the Python interpreter to use for the forecast runner.
     *
     * Honors FORECAST_PYTHON_BINARY / services.forecast.python_binary first.
     * If that value is a bare command (not an absolute path) and a project
     * virtual environment exists, prefer the venv interpreter.
     */
    public function resolvePythonBinary(): string
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
    public function processEnv(): array
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
        $today = ReportingDateService::reportingDateString();

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