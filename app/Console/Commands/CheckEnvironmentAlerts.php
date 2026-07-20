<?php

namespace App\Console\Commands;

use App\Models\EnvironmentalLog;
use App\Services\EnvironmentAlertService;
use Illuminate\Console\Command;

class CheckEnvironmentAlerts extends Command
{
    protected $signature = 'alerts:check-environment';

    protected $description = 'Re-check recent environmental logs against alert thresholds and create alerts as needed';

    public function handle(): int
    {
        $logs = EnvironmentalLog::where('recorded_at', '>=', now()->subHours(24))->get();

        if ($logs->isEmpty()) {
            $this->info('No environmental logs found in the last 24 hours.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($logs as $log) {
            EnvironmentAlertService::check($log);
            $count++;
        }

        $this->info("Checked {$count} environmental log(s) for threshold violations.");

        return self::SUCCESS;
    }
}
