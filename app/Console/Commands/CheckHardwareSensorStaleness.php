<?php

namespace App\Console\Commands;

use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use Illuminate\Console\Command;

class CheckHardwareSensorStaleness extends Command
{
    private const STALE_THRESHOLD_MINUTES = 30;

    protected $signature = 'hardware:check-staleness
        {--threshold= : Override staleness threshold in minutes}
        {--dry-run : Report stale sensors without changing status}';

    protected $description = 'Mark active hardware sensors as faulty when they have not reported within the staleness threshold';

    public function handle(): int
    {
        $thresholdMinutes = (int) ($this->option('threshold') ?: self::STALE_THRESHOLD_MINUTES);
        $since = now()->subMinutes($thresholdMinutes);
        $dryRun = $this->option('dry-run');

        $this->info("Checking for active sensors with no report since {$since->toDateTimeString()} ({$thresholdMinutes} min threshold)...");

        $activeSensors = HardwareItem::where('status', 'active')
            ->whereIn('device_type', ['DHT22', 'IR_breakbeam'])
            ->where(function ($q) {
                $q->whereNotNull('cage_id')->orWhereNotNull('cage_slot_id');
            })
            ->get();

        if ($activeSensors->isEmpty()) {
            $this->info('No active sensors found to check.');
            return self::SUCCESS;
        }

        $this->line("Found {$activeSensors->count()} active sensor(s) to evaluate.");

        $staleCount = 0;

        foreach ($activeSensors as $sensor) {
            $cageId = $sensor->cage_id ?? $sensor->cageSlot?->cage_id;

            if (! $cageId) {
                continue;
            }

            $recentLog = EnvironmentalLog::where('cage_id', $cageId)
                ->where('recorded_at', '>=', $since)
                ->exists();

            if ($recentLog) {
                continue;
            }

            $staleCount++;

            $label = $sensor->serial_number ?: "{$sensor->device_type} #{$sensor->id}";

            if ($dryRun) {
                $this->warn("[DRY-RUN] Stale sensor: {$label} (Cage #{$cageId}) — no report in {$thresholdMinutes} min. Would mark as faulty.");
            } else {
                $sensor->update(['status' => 'faulty']);
                $this->warn("Marked faulty: {$label} (Cage #{$cageId}) — no report in {$thresholdMinutes} min.");
            }
        }

        if ($staleCount === 0) {
            $this->info('All active sensors have reported recently. No stale sensors found.');
        } else {
            $this->line("Total stale sensor(s): {$staleCount}");
        }

        return self::SUCCESS;
    }
}
