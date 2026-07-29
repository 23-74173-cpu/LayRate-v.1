<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ForecastDebug extends Command
{
    protected $signature = 'forecast:debug
                            {--scope=cage : farm, breed, or cage}
                            {--cage= : Cage code to check}
                            {--breed= : Breed to check}';

    protected $description = 'Dump forecast data state, sufficiency check, and Python binary resolution';

    public function handle(): int
    {
        $scope = $this->option('scope');
        $cageCode = $this->option('cage');
        $breed = $this->option('breed');

        $this->line('=== FORECAST DEBUG ===');
        $total = DB::table('forecast_input_records')->count();
        $this->line("Total rows in forecast_input_records: {$total}");

        $cages = DB::table('forecast_input_records')->distinct()->pluck('cage_code')->sort()->values();
        $this->line("Cages: " . json_encode($cages));

        $breeds = DB::table('forecast_input_records')->whereNotNull('breed')->distinct()->pluck('breed')->sort()->values();
        $this->line("Breeds: " . json_encode($breeds));

        $distinctDates = DB::table('forecast_input_records')->distinct()->count('date');
        $minDate = DB::table('forecast_input_records')->min('date');
        $maxDate = DB::table('forecast_input_records')->max('date');
        $this->line("Distinct dates: {$distinctDates} ({$minDate} to {$maxDate})");

        $this->line('');
        $this->line("--- Sufficiency by scope ---");
        $farmOk = $distinctDates >= 90;
        $this->line("Farm: {$distinctDates}/90 (" . ($farmOk ? 'YES' : 'NO') . ")");

        foreach ($cages as $c) {
            $cnt = DB::table('forecast_input_records')
                ->whereNotNull('date')->whereNotNull('cage_code')
                ->whereRaw("TRIM(cage_code) != ''")
                ->where('cage_code', $c)->count();
            $this->line("Cage {$c}: {$cnt}/90 (" . ($cnt >= 90 ? 'YES' : 'NO') . ")");
        }

        foreach ($breeds as $b) {
            $cnt = DB::table('forecast_input_records')
                ->whereNotNull('date')->whereNotNull('cage_code')
                ->whereRaw("TRIM(cage_code) != ''")
                ->where('breed', $b)->count();
            $this->line("Breed {$b}: {$cnt}/90 (" . ($cnt >= 90 ? 'YES' : 'NO') . ")");
        }

        $this->line('');
        $this->line("--- Python binary ---");
        try {
            $reflection = new \ReflectionMethod(\App\Http\Controllers\ForecastController::class, 'resolvePythonBinary');
            $reflection->setAccessible(true);
            $controller = app()->make(\App\Http\Controllers\ForecastController::class);
            $python = $reflection->invoke($controller);
            $this->line("Resolved: {$python}");
            $this->line("Exists: " . (file_exists($python) ? 'YES' : 'NO'));
            if (file_exists($python)) {
                $out = shell_exec(escapeshellcmd($python) . " -c \"import pymysql; import pandas; print('pymysql ' + pymysql.__version__ + ', pandas ' + pandas.__version__)\" 2>&1");
                $this->line("Packages: " . trim($out ?? 'ERROR'));
            }
        } catch (\Throwable $e) {
            $this->error("Reflection error: " . $e->getMessage());
        }

        $this->line('');
        $this->line("--- Logs (last 20 forecast lines) ---");
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $lines = `grep -i 'forecast' {$logPath} | tail -20`;
            $this->line($lines ?: '(no forecast log entries)');
        } else {
            $this->line('(log file not found)');
        }

        $this->line('');
        $this->line("--- Process env check ---");
        try {
            $reflection2 = new \ReflectionMethod(\App\Http\Controllers\ForecastController::class, 'processEnv');
            $reflection2->setAccessible(true);
            $env = $reflection2->invoke($controller);
            $safe = collect($env)->except(['DB_PASSWORD'])->toArray();
            $this->line(json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            $this->error("Reflection error: " . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
