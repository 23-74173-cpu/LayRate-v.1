<?php

namespace App\Console\Commands;

use App\Services\ForecastGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        $service = app(ForecastGenerationService::class);

        $this->line('=== FORECAST DEBUG ===');
        $total = DB::table('production_logs')->count();
        $this->line("Total rows in production_logs: {$total}");

        $cages = $service->recordedCages();
        $this->line("Cages: " . json_encode($cages));

        $breeds = $service->recordedBreeds();
        $this->line("Breeds: " . json_encode($breeds));

        $this->line('');
        $this->line("--- Sufficiency by scope ---");
        $farm = $service->dataSufficiency('farm');
        $this->line("Farm: {$farm['current_count']}/90 (" . ($farm['has_enough'] ? 'YES' : 'NO') . ")");

        foreach ($cages as $c) {
            $s = $service->dataSufficiency('cage', $c);
            $this->line("Cage {$c}: {$s['current_count']}/90 (" . ($s['has_enough'] ? 'YES' : 'NO') . ")");
        }

        foreach ($breeds as $b) {
            $s = $service->dataSufficiency('breed', null, $b);
            $this->line("Breed {$b}: {$s['current_count']}/90 (" . ($s['has_enough'] ? 'YES' : 'NO') . ")");
        }

        $this->line('');
        $this->line("--- Python binary ---");
        try {
            $python = $service->resolvePythonBinary();
            $this->line("Resolved: {$python}");
            $this->line("Exists: " . (file_exists($python) ? 'YES' : 'NO'));
            if (file_exists($python)) {
                $out = shell_exec(escapeshellcmd($python) . " -c \"import pymysql; import pandas; print('pymysql ' + pymysql.__version__ + ', pandas ' + pandas.__version__)\" 2>&1");
                $this->line("Packages: " . trim($out ?? 'ERROR'));
            }
        } catch (\Throwable $e) {
            $this->error("Python resolution error: " . $e->getMessage());
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
            $env = $service->processEnv();
            $safe = collect($env)->except(['DB_PASSWORD'])->toArray();
            $this->line(json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            $this->error("Process env error: " . $e->getMessage());
        }

        return self::SUCCESS;
    }
}