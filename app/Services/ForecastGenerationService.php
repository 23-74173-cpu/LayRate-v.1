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
    /**
     * Distinct cages that currently have model-eligible records (completed
     * days with production + environmental data).
     */
    public function recordedCages(): Collection
    {
        return DB::table('production_logs as pl')
            ->join('cage_slots as cs', 'pl.cage_slot_id', '=', 'cs.id')
            ->join('cages as c', 'cs.cage_id', '=', 'c.id')
            ->whereNotNull('c.cage_code')
            ->whereRaw("TRIM(c.cage_code) != ''")
            ->where('pl.is_demo', false)
            ->where('pl.log_date', '<', ReportingDateService::reportingDateString())
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('environmental_logs as el')
                    ->whereColumn('el.cage_id', 'c.id')
                    ->where('el.is_demo', false)
                    ->whereRaw('DATE(el.recorded_at) = pl.log_date');
            })
            ->distinct()
            ->pluck('c.cage_code')
            ->filter()
            ->sort()
            ->values();
    }

    /**
     * Distinct breeds of active hens — the breed dropdown for breed-scope
     * forecasts.
     */
    public function recordedBreeds(): Collection
    {
        return DB::table('hens')
            ->where('is_active', true)
            ->whereNotNull('breed')
            ->whereRaw("TRIM(breed) != ''")
            ->distinct()
            ->pluck('breed')
            ->filter()
            ->sort()
            ->values();
    }

    public function farmHistorical(int $days = 14): Collection
    {
        return $this->dailyTotalsQuery()
            ->orderByDesc('date')
            ->limit($days)
            ->get()
            ->map(fn ($row) => tap((object) [
                'log_date' => $row->date,
                'egg_count' => (int) $row->egg_count,
                'hen_count' => (int) $row->hen_count,
                'hdep' => $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0,
            ], fn ($r) => $r))
            ->reverse()
            ->values();
    }

    public function breedHistorical(string $breed): Collection
    {
        return $this->dailyTotalsQuery()
            ->whereExists($this->firstHenBreedClosure($breed))
            ->orderByDesc('date')
            ->limit(14)
            ->get()
            ->map(fn ($row) => tap((object) [
                'log_date' => $row->date,
                'egg_count' => (int) $row->egg_count,
                'hen_count' => (int) $row->hen_count,
                'hdep' => $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0,
            ], fn ($r) => $r))
            ->reverse()
            ->values();
    }

    public function cageHistorical(string $cageCode): Collection
    {
        return $this->dailyTotalsQuery()
            ->where('c.cage_code', $cageCode)
            ->orderByDesc('date')
            ->limit(14)
            ->get()
            ->map(fn ($row) => tap((object) [
                'log_date' => $row->date,
                'egg_count' => (int) $row->egg_count,
                'hen_count' => (int) $row->hen_count,
                'hdep' => $row->hen_count > 0 ? round(($row->egg_count / $row->hen_count) * 100, 2) : 0,
            ], fn ($r) => $r))
            ->reverse()
            ->values();
    }

    /**
     * Daily farm-wide egg/hen totals from production_logs (completed days),
     * optionally scoped to a cage or breed for the historical chart.
     */
    private function dailyTotalsQuery()
    {
        return DB::table('production_logs as pl')
            ->join('cage_slots as cs', 'pl.cage_slot_id', '=', 'cs.id')
            ->join('cages as c', 'cs.cage_id', '=', 'c.id')
            ->select(
                'pl.log_date as date',
                DB::raw('COALESCE(SUM(pl.egg_count), 0) as egg_count'),
                DB::raw('COALESCE(SUM(pl.hen_count), 0) as hen_count')
            )
            ->whereNotNull('pl.log_date')
            ->whereNotNull('c.cage_code')
            ->whereRaw("TRIM(c.cage_code) != ''")
            ->where('pl.is_demo', false)
            ->where('pl.log_date', '<', ReportingDateService::reportingDateString())
            ->groupBy('pl.log_date');
    }

    /**
     * Actual (recorded) daily egg production for a calendar month, keyed by
     * 'Y-m-d' so the calendar view can look a date up in O(1).
     *
     * Unlike farmHistorical()/cageHistorical()/breedHistorical() this is not
     * capped to the trailing 14 days and takes an explicit inclusive range, so
     * the Production Calendar can show any month the user navigates to.
     *
     * @param  string  $scope  'farm' | 'cage' | 'breed'
     * @return Collection<string, array{egg_count:int, hen_count:int, hdep:float}>
     */
    public function productionForRange(string $scope, string $startDate, string $endDate, ?string $cageCode = null, ?string $breed = null): Collection
    {
        $query = $this->dailyTotalsQuery()
            ->whereBetween('pl.log_date', [$startDate, $endDate]);

        if ($scope === 'cage' && $cageCode) {
            $query->where('c.cage_code', $cageCode);
        } elseif ($scope === 'breed' && $breed) {
            $query->whereExists($this->firstHenBreedClosure($breed));
        }

        $rows = $query
            ->orderBy('pl.log_date')
            ->get();

        return $rows->mapWithKeys(function ($row) {
            $date = is_object($row->date)
                ? $row->date->format('Y-m-d')
                : substr((string) $row->date, 0, 10);

            return [$date => [
                'egg_count' => (int) $row->egg_count,
                'hen_count' => (int) $row->hen_count,
                'hdep' => $row->hen_count > 0
                    ? round(($row->egg_count / $row->hen_count) * 100, 2)
                    : 0,
            ]];
        });
    }

    /**
     * Earliest date with a real (non-demo) production log, or null when the
     * farm has no recorded production at all.
     *
     * Used to bound the Production Calendar's year dropdown now that past
     * months are navigable — without a data-derived floor the dropdown would
     * either start arbitrarily far back or exclude real history.
     */
    public function earliestProductionDate(): ?string
    {
        $min = DB::table('production_logs')
            ->where('is_demo', false)
            ->whereNotNull('log_date')
            ->min('log_date');

        if ($min === null) {
            return null;
        }

        return substr((string) $min, 0, 10);
    }

    /**
     * WHERE EXISTS closure: a cage whose FIRST active hen (lowest id) has the
     * given breed — matching the breed attribute the modeling dataset uses.
     *
     * @return \Closure
     */
    private function firstHenBreedClosure(string $breed)
    {
        return function ($q) use ($breed) {
            $q->selectRaw('1')
                ->from('hens as h')
                ->join('cage_slots as ch', 'h.cage_slot_id', '=', 'ch.id')
                ->whereColumn('ch.cage_id', 'c.id')
                ->where('h.is_active', true)
                ->where('h.breed', $breed)
                ->whereRaw('h.id = (SELECT MIN(h2.id) FROM hens h2 JOIN cage_slots ch2 ON h2.cage_slot_id = ch2.id WHERE ch2.cage_id = c.id AND h2.is_active = 1)');
        };
    }

    /**
     * Determine whether the aggregated production tables have enough historical
     * data for the requested scope — the old forecast_input_records sufficiency
     * check, read directly from the native tables.
     *
     * Whole farm needs at least 90 distinct dates. Per-cage / per-breed need at
     * least 90 rows for the selected cage or breed.
     */
    /**
     * @param  string|null  $beforeDate  Upper bound for a backtest: only
     *                                   production strictly before this date
     *                                   counts, mirroring the training cut the
     *                                   Python runner applies. Without it a
     *                                   backtest would be validated against
     *                                   records from after its own target day,
     *                                   which the model never sees.
     */
    public function dataSufficiency(string $scope, ?string $cageCode = null, ?string $breed = null, ?string $beforeDate = null): array
    {
        $base = DB::table('production_logs as pl')
            ->join('cage_slots as cs', 'pl.cage_slot_id', '=', 'cs.id')
            ->join('cages as c', 'cs.cage_id', '=', 'c.id')
            ->whereNotNull('pl.log_date')
            ->whereNotNull('c.cage_code')
            ->whereRaw("TRIM(c.cage_code) != ''")
            ->where('pl.is_demo', false)
            ->where('pl.log_date', '<', ReportingDateService::reportingDateString())
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('environmental_logs as el')
                    ->whereColumn('el.cage_id', 'c.id')
                    ->where('el.is_demo', false)
                    ->whereRaw('DATE(el.recorded_at) = pl.log_date')
                    ->whereNotNull('el.temperature_c')
                    ->whereNotNull('el.humidity_pct');
            });

        if ($beforeDate !== null) {
            $base->where('pl.log_date', '<', $beforeDate);
        }

        $countQuery = (clone $base)->selectRaw('COUNT(DISTINCT pl.log_date) as cnt');

        $uniqueDates = match (true) {
            $scope === 'cage' && $cageCode => (int) $countQuery->where('c.cage_code', $cageCode)->value('cnt'),
            $scope === 'breed' && $breed => (int) $countQuery->whereExists($this->firstHenBreedClosure($breed))->value('cnt'),
            default => (int) $countQuery->value('cnt'),
        };

        $perCage = (clone $base)
            ->select('c.cage_code', DB::raw('COUNT(DISTINCT pl.log_date) as unique_dates'))
            ->groupBy('c.cage_code')
            ->orderBy('c.cage_code')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->cage_code => (int) $row->unique_dates])
            ->toArray();

        $daysRemaining = max(0, 90 - $uniqueDates);

        Log::info('Forecast data sufficiency check', [
            'scope' => $scope,
            'cage_code' => $cageCode,
            'breed' => $breed,
            'unique_dates' => $uniqueDates,
            'threshold' => 90,
            'has_enough' => $uniqueDates >= 90,
            'days_remaining' => $daysRemaining,
            'per_cage' => $perCage,
        ]);

        return [
            'has_enough' => $uniqueDates >= 90,
            'current_count' => $uniqueDates,
            'days_remaining' => $daysRemaining,
            'per_cage' => $perCage,
        ];
    }

    /**
     * Full modeling dataset aggregated from the native tables — the direct
     * replacement for the old forecast_input_records read. Each row matches
     * the export columns (date, cage_code, breed, flock_age_weeks, hen_count,
     * egg_count, temperature_c, humidity_percent, crude_protein_percent,
     * feed_consumed_kg, mortality_count).
     */
    public function datasetRows(?string $cageCode = null, ?string $breed = null): Collection
    {
        $query = DB::table('production_logs as pl')
            ->join('cage_slots as cs', 'pl.cage_slot_id', '=', 'cs.id')
            ->join('cages as c', 'cs.cage_id', '=', 'c.id')
            ->select([
                'pl.log_date as date',
                'c.cage_code',
                DB::raw('(SELECT h.breed FROM hens h JOIN cage_slots ch ON h.cage_slot_id = ch.id WHERE ch.cage_id = c.id AND h.is_active = 1 ORDER BY h.id LIMIT 1) as breed'),
                DB::raw('(SELECT h.flock_age_weeks FROM hens h JOIN cage_slots ch2 ON h.cage_slot_id = ch2.id WHERE ch2.cage_id = c.id AND h.is_active = 1 ORDER BY h.id LIMIT 1) as flock_age_weeks'),
                DB::raw('COALESCE(SUM(pl.hen_count), 0) as hen_count'),
                DB::raw('COALESCE(SUM(pl.egg_count), 0) as egg_count'),
                DB::raw('(SELECT ROUND(AVG(el.temperature_c), 2) FROM environmental_logs el WHERE el.cage_id = c.id AND el.is_demo = 0 AND DATE(el.recorded_at) = pl.log_date) as temperature_c'),
                DB::raw('(SELECT ROUND(AVG(el.humidity_pct), 2) FROM environmental_logs el WHERE el.cage_id = c.id AND el.is_demo = 0 AND DATE(el.recorded_at) = pl.log_date) as humidity_percent'),
                DB::raw('(SELECT fb.crude_protein FROM feed_consumption_logs fcl LEFT JOIN feed_batches fb ON fcl.feed_batch_id = fb.id WHERE fcl.cage_id = c.id AND fcl.log_date = pl.log_date ORDER BY fcl.id DESC LIMIT 1) as crude_protein_percent'),
                DB::raw('(SELECT fcl.feed_consumed_kg FROM feed_consumption_logs fcl WHERE fcl.cage_id = c.id AND fcl.log_date = pl.log_date ORDER BY fcl.id DESC LIMIT 1) as feed_consumed_kg'),
                DB::raw('(SELECT COALESCE(SUM(ml.count), 0) FROM mortality_logs ml WHERE ml.cage_id = c.id AND ml.log_date = pl.log_date) as mortality_count'),
            ])
            ->whereNotNull('pl.log_date')
            ->whereNotNull('c.cage_code')
            ->whereRaw("TRIM(c.cage_code) != ''")
            ->where('pl.is_demo', false)
            ->where('pl.log_date', '<', ReportingDateService::reportingDateString())
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('environmental_logs as el')
                    ->whereColumn('el.cage_id', 'c.id')
                    ->where('el.is_demo', false)
                    ->whereRaw('DATE(el.recorded_at) = pl.log_date')
                    ->whereNotNull('el.temperature_c')
                    ->whereNotNull('el.humidity_pct');
            })
            ->groupBy('pl.log_date', 'c.id', 'c.cage_code')
            ->orderBy('pl.log_date')
            ->orderBy('c.cage_code');

        if ($cageCode) {
            $query->where('c.cage_code', $cageCode);
        }

        if ($breed) {
            $query->whereExists($this->firstHenBreedClosure($breed));
        }

        // Per the deployer's flock-age convention, age is anchored to the LATEST
        // egg-recorded date in the download and decrements 1 week per week back.
        $records = $query->get();

        $latestByCage = $records
            ->groupBy('cage_code')
            ->map(fn ($rows) => $rows->max(fn ($r) => $r->date));

        return $records->map(function ($row) use ($latestByCage) {
            $row = (object) $row;
            $weekSpan = ($latestByCage[$row->cage_code] && $row->date)
                ? (strtotime($latestByCage[$row->cage_code]) - strtotime($row->date)) / 604800.0
                : 0.0;
            $row->flock_age_weeks = round(max(0.0, (float) $row->flock_age_weeks - $weekSpan), 1);
            return $row;
        });
    }

    /**
     * Execute the Python forecasting pipeline and optionally persist results.
     *
     * @param  Collection  $historical  Kept for signature/backward compatibility; Python loads its own data.
     * @return array{forecasts: Collection, metrics: array, recommended_model: string}
     *
     * $manualParams are captured from the real HTTP request up front and passed
     * in — a queue worker has no matching request, so they can never be read
     * from request() here.
     */
    public function generateForecast(?Cage $cage, string $cageCode, ?string $breed, Collection $historical, int $horizon, bool $save = false, ?string $startDate = null, array $manualParams = [], ?int $forecastRunId = null): array
    {
        $result = $this->executePythonForecast($cageCode, $breed, $horizon, $manualParams, $startDate);

        Log::info('Forecast generation result', [
            'recommended_model' => $result['recommended_model'] ?? null,
            'metrics' => $result['metrics'] ?? [],
            'forecast_values' => array_slice(array_map(fn ($f) => [
                'date' => $f['date'] ?? null,
                'predicted_egg_count' => $f['predicted_egg_count'] ?? null,
            ], $result['forecast'] ?? []), 0, 5),
        ]);

        $forecasts = $save
            ? $this->persistForecasts($result, $cage, $breed, $forecastRunId)
            : $this->buildForecastCollection($result, $cage, $breed, $forecastRunId);

        return [
            'forecasts' => $forecasts,
            'metrics' => $result['metrics'] ?? [],
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
            $command[] = '--'.str_replace('_', '-', $key);
            $command[] = (string) $value;
        }

        $process = new Process($command, base_path());
        $process->setTimeout(300);
        $process->setEnv($this->processEnv());

        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $stdOutput = trim($process->getOutput());
            $pythonError = $errorOutput ?: $stdOutput;

            if (str_contains($pythonError, 'No module named')) {
                throw new RuntimeException(
                    'Forecast Python environment is missing required packages. '.
                    'Install dependencies with: pip install -r forecast-api/requirements.txt '.
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
            throw new RuntimeException('Invalid JSON from forecast runner: '.$output);
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
            'DB_HOST' => config('database.connections.mysql.host', '127.0.0.1'),
            'DB_PORT' => (string) config('database.connections.mysql.port', 3306),
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
     *
     * Every run is retained: the rows are appended and tagged with the
     * ForecastRun that produced them, and any earlier prediction for the same
     * (cage, breed, target_date) is soft-superseded rather than deleted. That
     * keeps a full audit trail so a prediction can later be compared against
     * the actual production recorded for its target date.
     *
     * Supersession is keyed on target_date, not on forecast_date. That matters
     * now that backtests are allowed: a backtest of a past date supersedes
     * only that past date's prediction and leaves unrelated forward forecasts
     * for other dates untouched.
     *
     * Public so callers holding runner output can persist it against a run
     * without routing through generateForecast()'s Python subprocess.
     */
    public function persistForecasts(array $result, ?Cage $cage, ?string $breed, ?int $forecastRunId = null): Collection
    {
        $collection = $this->buildForecastCollection($result, $cage, $breed, $forecastRunId);

        DB::transaction(function () use ($collection) {
            foreach ($collection as $forecast) {
                $this->supersedeExistingFor($forecast);
                $forecast->save();
            }
        });

        return $collection;
    }

    /**
     * Mark the current live prediction for a target date as superseded, so the
     * row about to be written becomes the new live one. Runs inside a
     * transaction with the insert so the calendar never renders a date with
     * zero live predictions.
     *
     * Scope identity is the (cage_id, breed) pair: farm is (null, null),
     * breed is (null, <breed>), cage is (<cage_id>, null). The comparisons are
     * built from the incoming row so each shape matches only its own twin.
     */
    private function supersedeExistingFor(Forecast $forecast): void
    {
        Forecast::query()
            ->whereNull('superseded_at')
            ->whereDate('target_date', $forecast->target_date)
            ->where(function ($query) use ($forecast) {
                $forecast->cage_id === null
                    ? $query->whereNull('cage_id')
                    : $query->where('cage_id', $forecast->cage_id);

                $forecast->breed === null
                    ? $query->whereNull('breed')
                    : $query->where('breed', $forecast->breed);
            })
            ->update(['superseded_at' => now()]);
    }

    /**
     * Build an in-memory collection of Forecast models from runner output.
     *
     * A row is flagged is_backtest when its target date is strictly before the
     * reporting date — i.e. it scores a completed day rather than predicting
     * one. The reporting date itself is not flagged: that day is still in
     * progress and has no comparable actual yet.
     */
    private function buildForecastCollection(array $result, ?Cage $cage, ?string $breed, ?int $forecastRunId = null): Collection
    {
        $forecasts = collect();
        $today = ReportingDateService::reportingDateString();

        foreach ($result['forecast'] ?? [] as $item) {
            $forecasts->push(new Forecast([
                'cage_id' => $cage?->id,
                'breed' => $breed,
                'forecast_date' => $today,
                'target_date' => $item['date'],
                'predicted_egg_count' => $item['predicted_egg_count'],
                'forecast_run_id' => $forecastRunId,
                'is_backtest' => $item['date'] < $today,
            ]));
        }

        return $forecasts;
    }
}
