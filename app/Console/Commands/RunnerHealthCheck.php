<?php

namespace App\Console\Commands;

use App\Models\Alert;
use Illuminate\Console\Command;

class RunnerHealthCheck extends Command
{
    protected $signature = 'runner:health-check';

    protected $description = 'Check GitHub Actions runner status and create an alert if offline';

    public function handle(): int
    {
        $serviceName = 'actions.runner.23-74173-cpu-LayRate-v.1.LayRatePI.service';

        $output = [];
        $exitCode = 0;
        exec("systemctl is-active {$serviceName} 2>/dev/null", $output, $exitCode);

        $status = trim(implode('', $output));

        if ($status === 'active') {
            $this->info("Runner service {$serviceName} is active.");

            $existing = Alert::where('alert_type', 'runner_offline')
                ->where('is_read', 0)
                ->first();

            if ($existing) {
                $existing->update(['is_read' => 1]);
                $this->info('Cleared previous runner_offline alert (marked as read).');
            }

            return self::SUCCESS;
        }

        $this->warn("Runner service {$serviceName} is {$status}.");

        $exists = Alert::where('alert_type', 'runner_offline')
            ->where('is_read', 0)
            ->exists();

        if ($exists) {
            $this->info('Runner offline alert already exists — not duplicating.');
            return self::SUCCESS;
        }

        Alert::create([
            'cage_id' => null,
            'alert_type' => 'runner_offline',
            'message' => "GitHub Actions runner (LayRatePI) is {$status}. Deployments will not work until it is restored.",
            'is_read' => 0,
            'triggered_at' => now(),
        ]);

        $this->info("Created runner_offline alert (status: {$status}).");

        return self::SUCCESS;
    }
}
