<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\EnvironmentalLog;
use App\Models\HardwareItem;
use App\Models\ProductionLog;
use App\Models\SensorOccupancyReading;
use Illuminate\Console\Command;

class CheckHardwareSensorStaleness extends Command
{
    private const DHT22_STALE_THRESHOLD_MINUTES = 60;

    private const IR_STALE_THRESHOLD_HOURS = 24;

    private const CALIBRATION_INTERVAL_DAYS = 90;

    protected $signature = 'hardware:check-staleness
        {--dht22-threshold= : Override DHT22 staleness threshold in minutes}
        {--ir-threshold= : Override IR break-beam staleness threshold in hours}
        {--calibration-days= : Override calibration interval in days}
        {--dry-run : Report stale/overdue sensors without changing status}';

    protected $description = 'Check active sensors for staleness (per-type thresholds) and hardware items for overdue calibration, creating alerts as needed';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->checkDht22Staleness($dryRun);
        $this->checkIrBreakbeamStaleness($dryRun);
        $this->checkCalibrationOverdue($dryRun);

        return self::SUCCESS;
    }

    private function checkDht22Staleness(bool $dryRun): void
    {
        $thresholdMinutes = (int) ($this->option('dht22-threshold') ?: self::DHT22_STALE_THRESHOLD_MINUTES);
        $since = now()->subMinutes($thresholdMinutes);

        $this->info("DHT22 — checking active sensors with no environmental_log since {$since->toDateTimeString()} ({$thresholdMinutes} min threshold)...");

        $sensors = HardwareItem::where('status', 'active')
            ->where('device_type', 'DHT22')
            ->whereNotNull('cage_id')
            ->get();

        if ($sensors->isEmpty()) {
            $this->info('DHT22: No active sensors found.');
            return;
        }

        $stale = 0;
        foreach ($sensors as $sensor) {
            $hasRecentLog = EnvironmentalLog::where('cage_id', $sensor->cage_id)
                ->where('recorded_at', '>=', $since)
                ->exists();

            if ($hasRecentLog) {
                continue;
            }

            $stale++;
            $label = $sensor->serial_number ?: "DHT22 #{$sensor->id}";
            $msg = "DHT22 sensor {$label} (Cage #{$sensor->cage_id}) — no reading in {$thresholdMinutes} minutes. Marked faulty.";

            if ($dryRun) {
                $this->warn("[DRY-RUN] {$msg}");
                continue;
            }

            $sensor->update(['status' => 'faulty']);

            Alert::create([
                'cage_id' => $sensor->cage_id,
                'alert_type' => 'sensor_stale',
                'message' => $msg,
                'is_read' => 0,
                'triggered_at' => now(),
            ]);

            $this->warn($msg);
        }

        $this->line("DHT22: {$stale} stale sensor(s) found.");
    }

    private function checkIrBreakbeamStaleness(bool $dryRun): void
    {
        $thresholdHours = (int) ($this->option('ir-threshold') ?: self::IR_STALE_THRESHOLD_HOURS);
        $since = now()->subHours($thresholdHours);

        $this->info("IR_breakbeam — checking active sensors with no reading since {$since->toDateTimeString()} ({$thresholdHours} hour threshold)...");

        $sensors = HardwareItem::where('status', 'active')
            ->where('device_type', 'IR_breakbeam')
            ->whereNotNull('cage_id')
            ->with('cage')
            ->get();

        if ($sensors->isEmpty()) {
            $this->info('IR_breakbeam: No active sensors found.');
            return;
        }

        $silent = 0;
        foreach ($sensors as $sensor) {
            $hasRecentReading = SensorOccupancyReading::where('hardware_item_id', $sensor->id)
                ->where('recorded_at', '>=', $since)
                ->exists();

            if ($hasRecentReading) {
                continue;
            }

            $label = $sensor->serial_number ?: "IR_breakbeam #{$sensor->id}";

            $cageId = $sensor->cage_id;
            $cageSlots = $sensor->cage?->cageSlots()->pluck('id');

            $hasRecentProduction = false;
            if ($cageSlots && $cageSlots->isNotEmpty()) {
                $hasRecentProduction = ProductionLog::whereIn('cage_slot_id', $cageSlots)
                    ->where('log_date', '>=', $since->toDateString())
                    ->exists();
            }

            $silent++;

            if ($hasRecentProduction) {
                $msg = "IR breakbeam sensor {$label} (Cage #{$cageId}) — no reading in {$thresholdHours} hours despite recent egg activity. Marked faulty.";
                if (! $dryRun) {
                    $sensor->update(['status' => 'faulty']);
                }
            } else {
                $msg = "IR breakbeam sensor {$label} (Cage #{$cageId}) — no reading in {$thresholdHours} hours. No egg activity detected this period either — sensor may be idle rather than faulty.";
            }

            if ($dryRun) {
                $this->warn("[DRY-RUN] {$msg}");
                continue;
            }

            Alert::create([
                'cage_id' => $cageId,
                'alert_type' => $hasRecentProduction ? 'sensor_stale' : 'sensor_no_activity',
                'message' => $msg,
                'is_read' => 0,
                'triggered_at' => now(),
            ]);

            if ($hasRecentProduction) {
                $this->warn("Marked faulty: {$msg}");
            } else {
                $this->warn($msg);
            }
        }

        $this->line("IR_breakbeam: {$silent} silent sensor(s) found.");
    }

    private function checkCalibrationOverdue(bool $dryRun): void
    {
        $intervalDays = (int) ($this->option('calibration-days') ?: self::CALIBRATION_INTERVAL_DAYS);
        $cutoff = now()->subDays($intervalDays)->startOfDay();

        $this->info("Calibration — checking active hardware items with last_calibration_date before {$cutoff->toDateString()} ({$intervalDays} day interval)...");

        $items = HardwareItem::where('status', 'active')
            ->whereNotNull('last_calibration_date')
            ->where('last_calibration_date', '<', $cutoff)
            ->get();

        if ($items->isEmpty()) {
            $this->info('Calibration: No overdue items found.');
            return;
        }

        $overdue = 0;
        foreach ($items as $item) {
            $overdue++;
            $label = $item->serial_number ?: "{$item->device_type} #{$item->id}";
            $lastCal = $item->last_calibration_date->toDateString();
            $daysSince = $item->last_calibration_date->diffInDays(now());
            $cageId = $item->cage_id ?? $item->cageSlot?->cage_id;

            $msg = "{$item->device_type} sensor {$label} — last calibrated {$lastCal} ({$daysSince} days ago). Overdue (interval: {$intervalDays} days).";

            if ($dryRun) {
                $this->warn("[DRY-RUN] {$msg}");
                continue;
            }

            Alert::create([
                'cage_id' => $cageId,
                'alert_type' => 'calibration_overdue',
                'message' => $msg,
                'is_read' => 0,
                'triggered_at' => now(),
            ]);

            $this->warn($msg);
        }

        $this->line("Calibration: {$overdue} overdue item(s) found.");
    }
}
