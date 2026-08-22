<?php

namespace App\Console\Commands;

use App\Services\DeviceHealthEvaluator;
use Illuminate\Console\Command;

class CheckHardwareHealth extends Command
{
    protected $signature = 'hardware:check-health';

    protected $description = 'Backstop for the hardware health state machine: elapsed-time escalations (15-min cadence; ingestion does the online/stale part live)';

    public function handle(DeviceHealthEvaluator $evaluator): int
    {
        $evaluator->runStalenessBackstop();

        return self::SUCCESS;
    }
}
